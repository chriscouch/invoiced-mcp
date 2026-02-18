<?php

namespace App\Tests\PaymentProcessing\Operations;

use App\AccountsReceivable\Enums\PaymentLinkStatus;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\PaymentLink;
use App\AccountsReceivable\Models\PaymentLinkItem;
use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Models\Payment;
use App\Core\I18n\Exception\MismatchedCurrencyException;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\StatsdClient;
use App\Core\Utils\Enums\ObjectType;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\InitiatedChargeFactory;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\Operations\ProcessPayment;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;
use Mockery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

class PaymentFlowReconcileTest extends AppTestCase
{
    private static PaymentFlow $flow;
    private static PaymentMethod $paymentMethod;
    private static PaymentLink $paymentLink;
    private static Invoice $invoice2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$paymentMethod = self::getTestDataFactory()->acceptsPaymentMethod(self::$company, PaymentMethod::CREDIT_CARD, AdyenGateway::ID);
        self::hasCustomer();

        $adyenAccount = new AdyenAccount();
        $adyenAccount->balance_account_id = 'test_account';
        $adyenAccount->saveOrFail();

        PaymentFlow::queryWithoutMultitenancyUnsafe()->where('identifier', ['nonexisting', '1234', '1235'])->delete();
        AdyenPaymentResult::query()->where('reference', '1234')->delete();

        self::$flow = new PaymentFlow();
        self::$flow->amount = 100;
        self::$flow->currency = 'eur';
        self::$flow->initiated_from = PaymentFlowSource::AutoPay;
        self::$flow->identifier = 'nonexisting';
        self::$flow->status = PaymentFlowStatus::CollectPaymentDetails;
        self::$flow->gateway = AdyenGateway::ID;
        self::$flow->saveOrFail();

        $paymentLink = new PaymentLink();
        $paymentLink->status = PaymentLinkStatus::Active;
        $paymentLink->reusable = true;
        $paymentLink->currency = 'usd';
        $paymentLink->customer = self::$customer;
        $paymentLink->saveOrFail();

        $item1 = new PaymentLinkItem();
        $item1->payment_link = $paymentLink;
        $item1->description = 'Item 1';
        $item1->amount = 100.10;
        $item1->saveOrFail();

        $item1 = new PaymentLinkItem();
        $item1->payment_link = $paymentLink;
        $item1->description = 'Item 2';
        $item1->amount = 100.01;
        $item1->saveOrFail();

        self::$paymentLink = $paymentLink;

        self::hasMerchantAccount(FlywireGateway::ID, 'gateway7_'.time());
        self::$merchantAccount->credentials = (object) [
            'flywire_portal_codes' => 'XYZ',
            'shared_secret' => 'test',
        ];
        self::$merchantAccount->saveOrFail();

        self::hasMerchantAccount(AdyenGateway::ID, 'gateway6_'.time());

        self::$invoice2 = self::getTestDataFactory()->createInvoice(self::$customer);
        self::hasInvoice();
        self::hasCredit();
        self::hasCreditNote();

        self::$estimate = new Estimate();
        self::$estimate->setCustomer(self::$customer);
        self::$estimate->items = [
            [
                'name' => 'Test Item',
                'description' => 'test',
                'quantity' => 1,
                'unit_cost' => 100,
            ],
        ];
        self::$estimate->deposit = 50;
        self::$estimate->saveOrFail();
    }

    private function getOperation(): PaymentFlowReconcile
    {
        $reconciller = new PaymentSourceReconciler();
        $reconciller->setStatsd(new StatsdClient());
        $processPayment = new ProcessPayment(
            Mockery::mock(PaymentGatewayFactory::class),
            Mockery::mock(LockFactory::class),
            self::getService('test.charge_reconciler'),
            Mockery::mock(InitiatedChargeFactory::class),
            Mockery::mock(GatewayLogger::class),
        );

        $saver = new PaymentFlowReconcile(
            $reconciller,
            $processPayment,
            self::getService('test.payment_link_customer_handler'),
            self::getService('test.payment_link_invoice_handler'),
            self::getService('test.transaction_manager'),
            self::getService('test.payment_link_processor'),
        );
        $saver->setStatsd(new StatsdClient());

        return $saver;
    }

    public function testFlowForm(): void
    {
        $operation = $this->getOperation();
        $logger = Mockery::mock(LoggerInterface::class);
        $operation->setLogger($logger);

        $this->makeApplications();

        $logger->shouldReceive('error')
            ->with('Customer not found for reference: nonexisting')
            ->once();
        $operation->reconcile(self::$flow, new PaymentFlowReconcileData(
            gateway: AdyenGateway::ID,
            status: Charge::SUCCEEDED,
            gatewayId: 'nocharge',
            amount: Money::fromDecimal('EUR', 63.6),
        ));

        self::$flow->customer = self::$customer;
        self::$flow->saveOrFail();

        $this->assertTrue(true);
    }

    /**
     * @depends testFlowForm
     */
    public function testWrongCurrency(): void
    {
        $operation = $this->getOperation();
        $logger = Mockery::mock(LoggerInterface::class);
        $operation->setLogger($logger);
        try {
            $operation->reconcile(self::$flow, new PaymentFlowReconcileData(
                gateway: AdyenGateway::ID,
                status: Charge::SUCCEEDED,
                gatewayId: 'nocharge',
                amount: Money::zero('USD'),
            ));
            $this->fail('Expected MismatchedCurrencyException not thrown');
        } catch (MismatchedCurrencyException) {
            $this->assertTrue(true);
        }
    }

    /**
     * @depends testWrongCurrency
     */
    public function testSuccess(): void
    {
        $result = new PaymentFlowReconcileData(
            gateway: AdyenGateway::ID,
            status: Charge::SUCCEEDED,
            gatewayId: 'nocharge',
            amount: Money::fromDecimal('USD', 10.05),
            brand: 'visa',
            funding: strtolower('CREDIT'),
            last4: '0000',
            expiry: '12/2022',
            country: 'US',
        );

        $operation = $this->getOperation();

        self::$flow->currency = 'usd';
        self::$flow->amount = 155.2;
        self::$flow->merchant_account = self::$merchantAccount;

        $logger = Mockery::mock(LoggerInterface::class);
        $operation->setLogger($logger);
        $logger->shouldReceive('error')
            ->with('Payment amount does not equal reference: nonexisting 63.6 10.05')
            ->once();
        $operation->reconcile(self::$flow, $result);

        $result->amount = Money::fromDecimal('USD', 63.6);
        $operation->reconcile(self::$flow, $result);

        /** @var Charge $charge */
        $charge = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', 'nocharge')
            ->one();
        $this->assertEquals(63.6, $charge->amount);
        $this->assertEquals(self::$customer->id, $charge->customer?->id);
        /** @var Payment $payment */
        $payment = $charge->payment;
        $this->assertEquals(63.6, $payment->amount);
        $this->assertEquals(0, $payment->balance);
        $this->assertEquals(1, $payment->applied);
        $this->assertEquals([
            [
                'type' => 'invoice',
                'amount' => 10.1,
                'invoice' => self::$invoice2->id,
            ],
            [
                'type' => 'invoice',
                'amount' => 20.2,
                'invoice' => self::$invoice->id,
            ],
            [
                'type' => 'estimate',
                'amount' => 30.3,
                'estimate' => self::$estimate->id,
            ],
            [
                'type' => 'credit_note',
                'amount' => 1.0,
                'document_type' => 'invoice',
                'credit_note' => self::$creditNote->id,
                'invoice' => self::$invoice2->id,
            ],
            [
                'type' => 'credit_note',
                'amount' => 1.0,
                'document_type' => 'estimate',
                'credit_note' => self::$creditNote->id,
                'estimate' => self::$estimate->id,
            ],
            [
                'type' => 'credit',
                'amount' => 1.0,
            ],
            [
                'type' => 'convenience_fee',
                'amount' => 2.0,
            ],
            [
                'type' => 'applied_credit',
                'amount' => 2.0,
                'document_type' => 'invoice',
                'invoice' => self::$invoice2->id,
            ],
        ], array_map(function ($applied) {
            unset($applied['id']);

            return $applied;
        }, $payment->applied_to));

        $this->assertEquals('paid', self::$invoice->refresh()->status);
    }

    public function testSubmitPaymentLinkAdyen(): void
    {
        self::$paymentMethod->merchant_account = self::$merchantAccount->id;
        self::$paymentMethod->saveOrFail();

        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = '1236';
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 200.11;
        $paymentFlow->customer = self::$customer;
        $paymentFlow->payment_link = self::$paymentLink;
        $paymentFlow->merchant_account = self::$merchantAccount;
        $paymentFlow->initiated_from = PaymentFlowSource::CustomerPortal;
        $paymentFlow->gateway = AdyenGateway::ID;
        $paymentFlow->saveOrFail();

        $result = new PaymentFlowReconcileData(
            gateway: AdyenGateway::ID,
            status: Charge::SUCCEEDED,
            gatewayId: 'CD94QRW5T3XW24V5',
            amount: Money::fromDecimal('USD', 10.05),
        );

        self::getService('test.payment_flow_manager')->saveResult($paymentFlow->identifier, [
            'additionalData' => [
                'expiryDate' => '3/2030',
                'cardBin' => '370000',
                'cardSummary' => '0002',
                'paymentMethod' => 'amex',
                'cardPaymentMethod' => 'amex',
                'fundingSource' => 'CREDIT',
                'issuerBin' => '',
                'cardIssuingCountry' => 'NL',
            ],
            'pspReference' => 'CD94QRW5T3XW24V5',
            'resultCode' => 'Authorised',
            'amount' => [
                'currency' => 'USD',
                'value' => 20011,
            ],
            'merchantReference' => 'hxs74kas0bc5t78etbyj915jse777qmo',
            'paymentMethod' => [
                'brand' => 'amex',
                'type' => 'scheme',
            ],
        ]);

        $operation = $this->getOperation();
        $logger = Mockery::mock(LoggerInterface::class);
        $operation->setLogger($logger);
        $logger->shouldReceive('error')
            ->with('Payment amount does not equal reference: 1236 200.11 10.05')
            ->once();
        $operation->reconcile($paymentFlow, $result);

        $result->amount = Money::fromDecimal('USD', 200.11);

        // create no invoice
        $operation->reconcile($paymentFlow, $result);
        /** @var Charge $charge */
        $charge = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', 'CD94QRW5T3XW24V5')
            ->one();
        $this->assertEquals(200.11, $charge->amount);
        $this->assertEquals(self::$customer->id, $charge->customer?->id);
        /** @var Payment $payment */
        $payment = $charge->payment;
        $this->assertEquals(200.11, $payment->amount);
        $this->assertEquals(0, $payment->balance);
        $this->assertEquals(1, $payment->applied);

        // create with invoice
        $payment->deleteOrFail();
        $operation->reconcile($paymentFlow, $result);
        /** @var Charge $charge */
        $charge = Charge::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', 'CD94QRW5T3XW24V5')
            ->one();
        $this->assertEquals(200.11, $charge->amount);
        $this->assertEquals(self::$customer->id, $charge->customer?->id);
        /** @var Payment $payment */
        $payment = $charge->payment;
        $this->assertEquals(200.11, $payment->amount);
        $this->assertEquals(0, $payment->balance);
        $this->assertEquals(1, $payment->applied);

        // application without attached invoice
        /** @var PaymentFlowApplication $application */
        $application = PaymentFlowApplication::where('payment_flow_id', $paymentFlow->id)->one();
        $application->invoice = null;
        $application->saveOrFail();
        $logger->shouldReceive('error')
            ->with('Invoice is missing document type for reference: 1236')
            ->once();
        $operation->reconcile($paymentFlow, $result);
    }

    private function makeApplications(): void
    {
        /** @var Connection $connection */
        $connection = self::getService('test.database');
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::Invoice->value,
            'amount' => 10.1,
            'invoice_id' => self::$invoice2->id,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::Invoice->value,
            'amount' => 20.2,
            'invoice_id' => self::$invoice->id,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::Estimate->value,
            'amount' => 30.3,
            'estimate_id' => self::$estimate->id,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::CreditNote->value,
            'amount' => 1,
            'credit_note_id' => self::$creditNote->id,
            'invoice_id' => self::$invoice2->id,
            'document_type' => ObjectType::Invoice->value,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::CreditNote->value,
            'amount' => 1,
            'credit_note_id' => self::$creditNote->id,
            'estimate_id' => self::$estimate->id,
            'document_type' => ObjectType::Estimate->value,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::Credit->value,
            'amount' => 1,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::ConvenienceFee->value,
            'amount' => 2,
        ]);
        $connection->insert('PaymentFlowApplications', [
            'payment_flow_id' => self::$flow->id,
            'type' => PaymentItemIntType::AppliedCredit->value,
            'amount' => 2,
            'invoice_id' => self::$invoice2->id,
            'document_type' => ObjectType::Invoice->value,
        ]);
    }
}
