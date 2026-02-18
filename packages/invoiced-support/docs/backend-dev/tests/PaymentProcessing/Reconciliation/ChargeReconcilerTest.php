<?php

namespace App\Tests\PaymentProcessing\Reconciliation;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Models\VendorPayment;
use App\AccountsReceivable\Enums\InvoiceStatus;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\CreditBalance;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Enums\AccountType;
use App\Core\Ledger\Repository\LedgerRepository;
use App\Core\Utils\RandomString;
use App\ActivityLog\Enums\EventType;
use App\ActivityLog\Libs\EventSpool;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Reconciliation\ChargeReconciler;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\CreditChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\EstimateChargeApplicationItem;
use App\PaymentProcessing\ValueObjects\InvoiceChargeApplicationItem;
use App\Tests\AppTestCase;
use Doctrine\DBAL\Connection;
use stdClass;

class ChargeReconcilerTest extends AppTestCase
{
    private static Invoice $invoice2;
    private static Company $company2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$company2 = self::getTestDataFactory()->createCompany();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
        self::hasCard();
        self::hasBankAccount();
        self::hasEstimate();

        self::$invoice2 = new Invoice();
        self::$invoice2->setCustomer(self::$customer);
        self::$invoice2->items = [['unit_cost' => 200]];
        self::$invoice2->save();
    }

    public static function tearDownAfterClass(): void
    {
        self::$company2->delete();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        EventSpool::enable();
    }

    public function testReconcile(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
        );

        $reconciler = $this->getReconciler();

        $split = new InvoiceChargeApplicationItem($amount, self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id(),
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test',
            'id' => $chargeModel->id(),
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment->id(),
            'payment_source' => self::$card->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'succeeded',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeSucceeded);

        $expected = [
            'customer' => self::$customer->id(),
            'invoice' => self::$invoice->id(),
            'credit_note' => null,
            'type' => Transaction::TYPE_CHARGE,
            'method' => PaymentMethod::CREDIT_CARD,
            'payment_source' => self::$card->toArray(),
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => 1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $payment->id(),
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        // reconciling the charge again should be blocked, returning the same charge that has been already reconciled
        $split = new InvoiceChargeApplicationItem($amount, self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);
        $this->assertSame($reconciler->reconcile($charge, $chargeApplication, null)?->id, $chargeModel->id());
    }

    public function testReconcileFailed(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_failed',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::FAILED,
            merchantAccount: null,
            source: null,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
            failureReason: 'Card declined',
        );

        $reconciler = $this->getReconciler();

        $split = new InvoiceChargeApplicationItem($amount, self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        // Failed charges should not create a payment
        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id(),
            'description' => null,
            'disputed' => false,
            'failure_message' => 'Card declined',
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_failed',
            'id' => $chargeModel->id(),
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => null,
            'payment_source' => null,
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'failed',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeFailed);
    }

    public function testReconcilePending(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_pending',
            method: PaymentMethod::ACH,
            status: Charge::PENDING,
            merchantAccount: null,
            source: null,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
        );

        $reconciler = $this->getReconciler();

        $split = new InvoiceChargeApplicationItem($amount, self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_pending',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment->id,
            'payment_source' => null,
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'pending',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargePending);

        $expected = [
            'customer' => self::$customer->id,
            'invoice' => self::$invoice->id,
            'credit_note' => null,
            'type' => Transaction::TYPE_CHARGE,
            'method' => PaymentMethod::ACH,
            'payment_source' => null,
            'status' => Transaction::STATUS_PENDING,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_pending',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => 1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $payment->id,
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        // invoice should be pending
        $this->assertEquals(InvoiceStatus::Pending->value, self::$invoice->refresh()->status);
        $this->assertFalse(self::$invoice->paid);

        // reconciling the charge again should be blocked
        $split = new InvoiceChargeApplicationItem($amount, self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);
        $this->assertSame($reconciler->reconcile($charge, $chargeApplication, null)?->id, $chargeModel->id());
    }

    public function testReconcileNoInvoice(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_no_invoice',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
        );

        $reconciler = $this->getReconciler();

        $split = new CreditChargeApplicationItem($amount);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_no_invoice',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment->id,
            'payment_source' => self::$card->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'succeeded',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeSucceeded);

        $expected = [
            'customer' => self::$customer->id,
            'invoice' => null,
            'credit_note' => null,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'payment_source' => self::$card->toArray(),
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_no_invoice',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => -1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $payment->id,
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        // reconciling the charge again should be blocked

        $split = new InvoiceChargeApplicationItem($amount, self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);
        $this->assertSame($reconciler->reconcile($charge, $chargeApplication, null)?->id, $chargeModel->id);
    }

    private function chargeBuilderClass(Money $amount, ?string $id = null): object
    {
        if (!$id) {
            $id = microtime();
        }

        return new class($amount, self::$customer, self::$card, $id) {
            public array $input = [];

            public array $response = [
                'primary' => null,
                'secondary' => [],
            ];

            public int $timestamp;

            public function __construct(Money $amount, Customer $customer, ?PaymentSource $card, string $id)
            {
                $this->input = [
                    'customer' => $customer,
                    'gateway' => 'invoiced',
                    'gatewayId' => $id,
                    'timestamp' => (int) mktime(0, 0, 0, 12, 2, 2016),
                    'method' => PaymentMethod::CREDIT_CARD,
                    'status' => Charge::SUCCEEDED,
                    'amount' => $amount,
                    'source' => $card,
                    'description' => '',
                    'merchantAccount' => null,
                ];
            }

            public function buildCharge(): ChargeValueObject
            {
                $charge = new ChargeValueObject(...$this->input);
                $this->timestamp = $charge->timestamp;

                return $charge;
            }

            public function expected(array $invoices, array $amounts, Payment $payment, Transaction $transaction): array
            {
                $result = [];
                $parent = null;
                foreach ($amounts as $key => $amount) {
                    $invoice = $invoices[$key] ? $invoices[$key]->id : null;
                    $result[] = $this->buildResponseItem($invoice, $payment, $parent, $amount);
                    $parent = $transaction->id;
                }

                return $result;
            }

            private function buildResponseItem(int $invoice, Payment $payment, ?int $parent, Money $amount): array
            {
                return [
                    'customer' => $this->input['customer']->id,
                    'invoice' => $invoice,
                    'credit_note' => null,
                    'type' => Transaction::TYPE_CHARGE,
                    'method' => PaymentMethod::CREDIT_CARD,
                    'payment_source' => $this->input['source']->toArray(),
                    'status' => Transaction::STATUS_SUCCEEDED,
                    'gateway' => 'invoiced',
                    'gateway_id' => 'ch_test_multiple',
                    'parent_transaction' => $parent,
                    'currency' => 'usd',
                    'amount' => $amount->toDecimal(),
                    'date' => $this->timestamp,
                    'notes' => null,
                    'metadata' => new stdClass(),
                    'estimate' => null,
                    'payment_id' => $payment->id,
                ];
            }
        };
    }

    public function testReconcileMultipleInvoices(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //
        $amount = new Money('usd', 200);

        $chargeBuilder = $this->chargeBuilderClass($amount, 'ch_test_multiple');

        $charge = $chargeBuilder->buildCharge(); /* @phpstan-ignore-line */

        $hundred = new Money('usd', 100);
        $amounts = [$hundred, $hundred];

        $invoices = [self::$invoice, self::$invoice2];

        $reconciler = $this->getReconciler();

        $splits = [
            new InvoiceChargeApplicationItem($amounts[0], self::$invoice),
            new InvoiceChargeApplicationItem($amounts[1], self::$invoice2),
        ];
        $chargeApplication = new ChargeApplication($splits, PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);
        $payment = $transaction->payment;

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 2.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_multiple',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment?->id,
            'payment_source' => $chargeBuilder->input['source']->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'succeeded',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeSucceeded);

        $data = Transaction::where('parent_transaction', $transaction)->all();

        $results = [$transaction];
        foreach ($data as $item) {
            $results[] = $item;
        }
        // clean up the real result
        $results = array_map(function (Transaction $item) {
            $arr = $item->toArray();
            foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
                unset($arr[$property]);
            }

            return $arr;
        }, $results);

        $expected = $chargeBuilder->expected($invoices, $amounts, $payment, $transaction); /* @phpstan-ignore-line */
        $this->assertEquals($results, $expected);

        // reconciling the charge again should be blocked

        $splits = [
            new InvoiceChargeApplicationItem($amount, self::$invoice),
            new InvoiceChargeApplicationItem($amount, self::$invoice2),
        ];
        $chargeApplication = new ChargeApplication($splits, PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);
        $this->assertSame($reconciler->reconcile($charge, $chargeApplication, null)?->id, $chargeModel->id);
    }

    public function testReconcileConvenienceFee(): void
    {
        $amount = new Money('usd', 200);
        $amounts = [new Money('usd', 100), new Money('usd', 100)];
        $reconciler = $this->getReconciler();
        $invoices = [self::$invoice, self::$invoice2];
        $estimates = [self::$estimate];

        $method = PaymentMethod::instance(self::$company, PaymentMethod::CREDIT_CARD);
        $data = $this->runConvenienceFeeCheck($amounts, $invoices, $amount, $method, $reconciler);
        $this->assertCount(0, $data);

        $data = $this->runConvenienceFeeCheck($amounts, $estimates, $amount, $method, $reconciler);
        $this->assertCount(0, $data);

        $method->convenience_fee = 100;
        $data = $this->runConvenienceFeeCheck($amounts, $invoices, $amount, $method, $reconciler);
        $this->assertCount(1, $data);
        $this->assertEquals(0.02, $data[0]->amount); /* @phpstan-ignore-line */

        $data = $this->runConvenienceFeeCheck($amounts, $estimates, $amount, $method, $reconciler);
        $this->assertCount(1, $data);
        $this->assertEquals(0.02, $data[0]->amount); /* @phpstan-ignore-line */

        foreach ([PaymentMethod::ACH,  PaymentMethod::BALANCE,  PaymentMethod::CASH,  PaymentMethod::CHECK,  PaymentMethod::DIRECT_DEBIT,  PaymentMethod::OTHER,  PaymentMethod::PAYPAL,  PaymentMethod::WIRE_TRANSFER] as $methodId) {
            $method->id = $methodId;
            $data = $this->runConvenienceFeeCheck($amounts, $invoices, $amount, $method, $reconciler);
            $this->assertCount(1, $data);

            // $amounts has length of 2, $estimates has length 1. Reconciler will pick up charge with no attached document
            // and reconcile it as advance payment, thus adding it to the credit balance.
            $data = $this->runConvenienceFeeCheck($amounts, $estimates, $amount, $method, $reconciler);
            $this->assertCount(1, $data);
        }
    }

    private function runConvenienceFeeCheck(array $amounts, array $invoices, Money $amount, PaymentMethod $method, ChargeReconciler $reconciler): iterable
    {
        $splits = [];
        foreach ($amounts as $key => $value) {
            $invoice = $invoices[$key] ?? null;
            if ($invoice instanceof Invoice) {
                $splits[] = new InvoiceChargeApplicationItem($value, $invoice);
            } elseif ($invoice instanceof Estimate) {
                $splits[] = new EstimateChargeApplicationItem($value, $invoice);
            } else {
                $splits[] = new CreditChargeApplicationItem($value);
            }
        }
        $chargeApplication = new ChargeApplication($splits, PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee($method, self::$customer);
        $chargeBuilder = $this->chargeBuilderClass($chargeApplication->getPaymentAmount());
        $charge = $chargeBuilder->buildCharge(); /* @phpstan-ignore-line */

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];

        return Transaction::where('parent_transaction', $transaction)
            ->where('notes', 'Convenience Fee')
            ->all();
    }

    public function testReconcilePendingMultipleInvoices(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 200);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_multiple_pending',
            method: PaymentMethod::ACH,
            status: Charge::PENDING,
            merchantAccount: null,
            source: self::$bankAccount,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
        );

        $amounts = [new Money('usd', 100), new Money('usd', 100)];
        $invoices = [self::$invoice, self::$invoice2];

        $reconciler = $this->getReconciler();

        $splits = [];
        foreach ($amounts as $key => $value) {
            $splits[] = new InvoiceChargeApplicationItem($value, $invoices[$key]);
        }
        $chargeApplication = new ChargeApplication($splits, PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 2.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_multiple_pending',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment->id,
            'payment_source' => self::$bankAccount->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'pending',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargePending);

        $expected = [
            'customer' => self::$customer->id,
            'invoice' => self::$invoice->id,
            'credit_note' => null,
            'type' => Transaction::TYPE_CHARGE,
            'method' => PaymentMethod::ACH,
            'payment_source' => self::$bankAccount->toArray(),
            'status' => Transaction::STATUS_PENDING,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_multiple_pending',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => 1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $payment->id,
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        $payment2 = Transaction::where('parent_transaction', $transaction)->oneOrNull();
        $this->assertInstanceOf(Transaction::class, $payment2);
        $expected = [
            'customer' => self::$customer->id,
            'invoice' => self::$invoice2->id,
            'credit_note' => null,
            'type' => Transaction::TYPE_CHARGE,
            'method' => PaymentMethod::ACH,
            'payment_source' => self::$bankAccount->toArray(),
            'status' => Transaction::STATUS_PENDING,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_multiple_pending',
            'parent_transaction' => $transaction->id,
            'currency' => 'usd',
            'amount' => 1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $payment->id,
        ];

        $arr = $payment2->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        // reconciling the charge again should be blocked
        $splits = [];
        foreach ($amounts as $key => $value) {
            $splits[] = new InvoiceChargeApplicationItem($value, $invoices[$key]);
        }
        $chargeApplication = new ChargeApplication($splits, PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);
        $this->assertSame($reconciler->reconcile($charge, $chargeApplication, null)?->id, $chargeModel->id);
    }

    public function testReconcileEstimateCharge(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        self::$estimate->deposit_paid = false;
        self::$estimate->deposit = 100;
        self::$estimate->saveOrFail();

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_estimate',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
        );

        $reconciler = $this->getReconciler();

        $split = new EstimateChargeApplicationItem($amount, self::$estimate);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        $originalBalance = CreditBalance::lookup(self::$customer)->toDecimal();

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_estimate',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment->id,
            'payment_source' => self::$card->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'succeeded',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeSucceeded);

        $this->assertInstanceOf(Transaction::class, $transaction);
        $expected = [
            'customer' => self::$customer->id,
            'invoice' => null,
            'credit_note' => null,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'payment_source' => self::$card->toArray(),
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_estimate',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => -1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => self::$estimate->id,
            'payment_id' => $payment->id,
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at',  'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        // should generate a credit / mark estimate as paid
        $this->assertEquals($originalBalance + 1, CreditBalance::lookup(self::$customer)->toDecimal());
        $this->assertTrue(self::$estimate->refresh()->deposit_paid);

        // reconciling the charge again should be blocked
        $split = new EstimateChargeApplicationItem($amount, self::$estimate);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);
        $this->assertSame($reconciler->reconcile($charge, $chargeApplication, null)?->id, $chargeModel->id);
    }

    public function testReconcileEstimateChargeFail(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        self::$estimate->deposit_paid = false;
        self::$estimate->deposit = 100;
        self::$estimate->saveOrFail();

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_estimate_fail',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::FAILED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
            failureReason: 'fail',
        );

        $reconciler = $this->getReconciler();

        $split = new EstimateChargeApplicationItem($amount, self::$estimate);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        $originalBalance = CreditBalance::lookup(self::$customer)->toDecimal();

        //
        // Call the method being tested
        //
        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        // Failed charges should not create a payment
        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => 'fail',
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_estimate_fail',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => null,
            'payment_source' => self::$card->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'failed',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeFailed);

        // should NOT generate a credit / mark estimate as paid
        $this->assertEquals($originalBalance, CreditBalance::lookup(self::$customer)->toDecimal());
        $this->assertFalse(self::$estimate->refresh()->deposit_paid);
    }

    public function testReconcileAdvanceCharge(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_advance',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
        );

        $reconciler = $this->getReconciler();

        $split = new CreditChargeApplicationItem($amount);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        $originalBalance = CreditBalance::lookup(self::$customer)->toDecimal();

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        $payment = $chargeModel?->payment;
        $this->assertInstanceOf(Payment::class, $payment);
        $transaction = $payment->getTransactions()[0];
        $this->assertInstanceOf(Transaction::class, $transaction);

        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => null,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_advance',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => $payment->id,
            'payment_source' => self::$card->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'succeeded',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeSucceeded);

        $expected = [
            'customer' => self::$customer->id,
            'invoice' => null,
            'credit_note' => null,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'method' => PaymentMethod::BALANCE,
            'payment_source' => self::$card->toArray(),
            'status' => Transaction::STATUS_SUCCEEDED,
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_advance',
            'parent_transaction' => null,
            'currency' => 'usd',
            'amount' => -1.0,
            'date' => $charge->timestamp,
            'notes' => null,
            'metadata' => new stdClass(),
            'estimate' => null,
            'payment_id' => $payment->id,
        ];

        $arr = $transaction->toArray();
        foreach (['id', 'object', 'created_at', 'updated_at', 'pdf_url'] as $property) {
            unset($arr[$property]);
        }
        $this->assertEquals($expected, $arr);

        // should generate a credit.
        $this->assertEquals($originalBalance + 1, CreditBalance::lookup(self::$customer)->toDecimal());
    }

    public function testReconcileAdvanceChargeFail(): void
    {
        //
        // Setup - Models, Mocks, etc.
        //

        $amount = new Money('usd', 100);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_advance_fail',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::FAILED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 0, 12, 2, 2016),
            failureReason: 'fail',
        );

        $reconciler = $this->getReconciler();

        $originalBalance = CreditBalance::lookup(self::$customer)->toDecimal();

        $split = new CreditChargeApplicationItem($amount);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $chargeApplication->applyConvenienceFee(PaymentMethod::instance(self::$company, $charge->method), self::$customer);

        //
        // Call the method being tested
        //

        $chargeModel = $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //

        // Failed charges should not create a payment
        $this->assertInstanceOf(Charge::class, $chargeModel);
        $expected = [
            'amount' => 1.0,
            'amount_refunded' => 0.0,
            'created_at' => $chargeModel->created_at,
            'currency' => 'usd',
            'customer_id' => self::$customer->id,
            'description' => null,
            'disputed' => false,
            'failure_message' => 'fail',
            'gateway' => 'invoiced',
            'gateway_id' => 'ch_test_advance_fail',
            'id' => $chargeModel->id,
            'merchant_account_id' => null,
            'merchant_account_transaction_id' => null,
            'object' => 'charge',
            'payment_flow_id' => null,
            'payment_id' => null,
            'payment_source' => self::$card->toArray(),
            'receipt_email' => null,
            'refunded' => false,
            'refunds' => [],
            'status' => 'failed',
            'updated_at' => $chargeModel->updated_at,
        ];
        $this->assertEquals($expected, $chargeModel->toArray());
        $this->assertHasEvent($chargeModel, EventType::ChargeFailed);

        // should NOT generate a credit.
        $this->assertEquals($originalBalance, CreditBalance::lookup(self::$customer)->toDecimal());
    }

    public function testReconcileChargeNotification(): void
    {
        $reconciler = $this->getReconciler();
        $notificationSpool = self::getService('test.notification_spool');
        $emailSpool = self::getService('test.email_spool');

        $charge = $this->makeRandomCharge();
        $applications = new ChargeApplication([], PaymentFlowSource::CustomerPortal);

        $reconciler->reconcile($charge, $applications, null);
        $this->assertEquals(0, $notificationSpool->size());
        $this->assertEquals(1, $emailSpool->size());

        $charge = $this->makeRandomCharge(Charge::FAILED);
        $notificationSpool->clear();
        $emailSpool->clear();

        $reconciler->reconcile($charge, $applications, null);
        $this->assertEquals(0, $notificationSpool->size());
        $this->assertEquals(0, $emailSpool->size());

        $charge = $this->makeRandomCharge();
        $applications = new ChargeApplication([], PaymentFlowSource::AutoPay);
        $notificationSpool->clear();
        $emailSpool->clear();

        $reconciler->reconcile($charge, $applications, null);
        $this->assertEquals(1, $notificationSpool->size());
        $this->assertEquals(1, $emailSpool->size());

        $charge = $this->makeRandomCharge(Charge::FAILED);
        $notificationSpool->clear();
        $emailSpool->clear();

        $reconciler->reconcile($charge, $applications, null);
        $this->assertEquals(1, $notificationSpool->size());
        $this->assertEquals(0, $emailSpool->size());
    }

    public function testVendorPayment(): void
    {
        $connection = $this->getTestDataFactory()->connectCompanies(self::$company, self::$company2);
        self::$customer->network_connection = $connection;
        self::$customer->saveOrFail();
        $doc = $this->getTestDataFactory()->createNetworkDocument(self::$company, self::$company2);
        self::$invoice->network_document = $doc;
        self::$invoice->saveOrFail();

        $this->getService('test.tenant')->runAs(self::$company2, function () use ($connection, $doc) {
            self::hasVendor();
            self::hasBill();
            self::$vendor->network_connection = $connection;
            self::$vendor->saveOrFail();
            self::$bill->network_document = $doc;
            self::$bill->saveOrFail();
        });

        $amount = new Money('usd', 101);
        $charge = new ChargeValueObject(
            customer: self::$customer,
            amount: $amount,
            gateway: 'invoiced',
            gatewayId: 'ch_test_vendor_pay',
            method: PaymentMethod::CREDIT_CARD,
            status: Charge::SUCCEEDED,
            merchantAccount: null,
            source: self::$card,
            description: '',
            timestamp: (int) mktime(0, 0, 1, 12, 2, 2016),
        );

        $reconciler = $this->getReconciler();

        $split = new InvoiceChargeApplicationItem(new Money('usd', 100), self::$invoice);
        $chargeApplication = new ChargeApplication([$split], PaymentFlowSource::Charge);
        $method = PaymentMethod::instance(self::$company, $charge->method);
        $method->convenience_fee = 100;
        $chargeApplication->applyConvenienceFee($method, self::$customer);

        //
        // Call the method being tested
        //

        $reconciler->reconcile($charge, $chargeApplication, null);

        //
        // Verify the results
        //
        $this->getService('test.tenant')->runAs(self::$company2, function () {
            $payment = VendorPayment::where('vendor_id', self::$vendor->id)->one();
            $items = $payment->getItems();
            $this->assertEquals([
                [
                    'amount' => 1.0,
                    'bill_id' => $items[0]->bill_id,
                    'created_at' => $items[0]->created_at,
                    'id' => $items[0]->id,
                    'type' => 'application',
                    'updated_at' => $items[0]->updated_at,
                    'vendor_credit_id' => null,
                    'vendor_payment_id' => $payment->id,
                ],
                [
                    'amount' => 0.01,
                    'bill_id' => null,
                    'created_at' => $items[1]->created_at,
                    'id' => $items[1]->id,
                    'type' => 'convenience_fee',
                    'updated_at' => $items[1]->updated_at,
                    'vendor_credit_id' => null,
                    'vendor_payment_id' => $payment->id,
                ],
            ], array_map(fn ($item) => $item->toArray(), $items));
        });

        /** @var Connection $connection */
        $ledgerRepo = new LedgerRepository(self::getService('test.database'));
        $ledger = $ledgerRepo->find('Accounts Payable - '.self::$company2->id);

        $account1 = $ledger?->chartOfAccounts->findOrCreate(ApAccounts::ConvenienceFee->value, AccountType::Expense, 'USD');
        $account2 = $ledger?->chartOfAccounts->findOrCreate(ApAccounts::AccountsPayable->value, AccountType::Expense, 'USD');
        $account3 = $ledger?->chartOfAccounts->findOrCreate(ApAccounts::Cash->value, AccountType::Expense, 'USD');
        $connection = self::getService('test.database');
        $entries = $connection->executeQuery('select entry_type, amount, account_id FROM LedgerEntries ORDER BY id DESC LIMIT 3')->fetchAllAssociative();
        $this->assertEquals([
            [
                'entry_type' => 'D',
                'amount' => 100,
                'account_id' => $account2,
            ],
            [
                'entry_type' => 'D',
                'amount' => 1,
                'account_id' => $account1,
            ],
            [
                'entry_type' => 'C',
                'amount' => 101,
                'account_id' => $account3,
            ],
        ], $entries);
    }

    private function getReconciler(): ChargeReconciler
    {
        return self::getService('test.charge_reconciler');
    }

    private function makeRandomCharge(string $status = Charge::SUCCEEDED): ChargeValueObject
    {
        return new ChargeValueObject(
            customer: self::$customer,
            amount: new Money('usd', 100),
            gateway: 'invoiced',
            gatewayId: RandomString::generate(),
            method: PaymentMethod::CREDIT_CARD,
            status: $status,
            merchantAccount: null,
            source: self::$card,
            description: '',
        );
    }
}
