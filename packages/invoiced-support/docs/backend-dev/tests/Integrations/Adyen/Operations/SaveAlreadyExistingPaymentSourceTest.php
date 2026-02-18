<?php

namespace App\Tests\Integrations\Adyen\Operations;

use App\AccountsReceivable\Models\Customer;
use App\Core\Statsd\StatsdClient;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\Integrations\Adyen\Operations\SaveAlreadyExistingPaymentSource;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\Tests\AppTestCase;
use Mockery;

class SaveAlreadyExistingPaymentSourceTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID, 'TEST_MERCHANT_ID', ['balance_account' => 'test_account']);
        self::acceptsCreditCards();
    }

    private function getOperation(
        PaymentSourceReconciler $paymentSourceReconciler,
        DeletePaymentInfo $deletePaymentInfo
    ): SaveAlreadyExistingPaymentSource {
        return new SaveAlreadyExistingPaymentSource($paymentSourceReconciler, $deletePaymentInfo);
    }

    public function testProcessDoesNothingIfReferenceNotFound(): void
    {
        $customer = new Customer();
        $customer->name = 'AutoPay Test';
        $customer->autopay = true;
        $customer->saveOrFail();

        $reference = 'non_existing_reference';

        $paymentSourceReconciler = Mockery::mock(PaymentSourceReconciler::class);
        $deletePaymentInfo = Mockery::mock(DeletePaymentInfo::class);

        $operation = $this->getOperation($paymentSourceReconciler, $deletePaymentInfo);

        $operation->process(self::$merchantAccount, $customer, $reference);

        $this->assertTrue(true);
    }

    public function testProcessSetsDefaultPaymentSource(): void
    {
        $customer = new Customer();
        $customer->name = 'AutoPay Test';
        $customer->autopay = true;
        $customer->saveOrFail();

        $reference = 'EXISTING_REF_123_' . time();

        AdyenPaymentResult::where('reference', $reference)->delete();
        PaymentFlow::where('identifier', $reference)->delete();

        $adyenResult = new AdyenPaymentResult();
        $adyenResult->reference = $reference;
        $resultData = [
            'additionalData' => [
                'tokenization.store.operationType' => 'alreadyExisting',
                'tokenization.storedPaymentMethodId' => 'token_abc123',
                'expiryDate' => '08/27',
                'funding' => 'credit',
                'cardSummary' => '4242',
                'cardIssuingCountry' => 'US',
            ],
            'paymentMethod' => [
                'brand' => 'visa'
            ]
        ];
        $json = json_encode($resultData, JSON_THROW_ON_ERROR);
        $adyenResult->result = $json;

        $adyenResult->saveOrFail();

        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = $reference;
        $paymentFlow->make_payment_source_default = true;
        $paymentFlow->status = PaymentFlowStatus::Succeeded;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 100.10;
        $paymentFlow->customer = $customer;
        $paymentFlow->initiated_from = PaymentFlowSource::CustomerPortal;
        $paymentFlow->gateway = AdyenGateway::ID;
        $paymentFlow->saveOrFail();

        $statsD = Mockery::mock(StatsdClient::class);
        $statsD->shouldReceive('increment')->withAnyArgs()->andReturnNull();

        $paymentSourceReconciler = new PaymentSourceReconciler();
        $paymentSourceReconciler->setStatsd($statsD);

        $deletePaymentInfo = Mockery::mock(DeletePaymentInfo::class);

        $operation = new SaveAlreadyExistingPaymentSource($paymentSourceReconciler, $deletePaymentInfo);

        $operation->process(self::$merchantAccount, $customer, $reference);

        $customer->refresh();

        $this->assertNotNull($customer->default_source_id);

        $cards = Card::where('customer_id', $customer->id)->execute();
        $this->assertCount(1, $cards);

        $card = $cards[0];
        $this->assertEquals($card->id, $customer->default_source_id);

        $this->assertEquals('visa', $card->brand);
        $this->assertEquals('4242', $card->last4);
        $this->assertEquals(8, $card->exp_month);
        $this->assertEquals(27, $card->exp_year);
        $this->assertEquals('credit', $card->funding);
        $this->assertEquals('US', $card->issuing_country);
    }


    public function testProcessSkipsIfAutopayIsDisabled(): void
    {
        $customer = new Customer();
        $customer->name = 'AutoPay Test';
        $customer->autopay = false;
        $customer->saveOrFail();

        $reference = 'REF_WITH_DISABLED_AUTOPAY';

        $adyenResult = new AdyenPaymentResult();
        $adyenResult->reference = $reference;
        $resultData = [
                'additionalData' => [
                    'tokenization.store.operationType' => 'alreadyExisting',
                    'tokenization.storedPaymentMethodId' => 'some_token',
                ],
                'paymentMethod' => [
                    'brand' => 'visa'
                ]
            ];
        $json = json_encode($resultData, JSON_THROW_ON_ERROR);
        $adyenResult->result = $json;
        $adyenResult->saveOrFail();

        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = $reference;
        $paymentFlow->make_payment_source_default = true;
        $paymentFlow->status = PaymentFlowStatus::Succeeded;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 100.10;
        $paymentFlow->customer = $customer;
        $paymentFlow->initiated_from = PaymentFlowSource::CustomerPortal;
        $paymentFlow->gateway = AdyenGateway::ID;
        $paymentFlow->saveOrFail();

        $paymentSourceReconciler = Mockery::mock(PaymentSourceReconciler::class);
        $deletePaymentInfo = Mockery::mock(DeletePaymentInfo::class);

        $customer = new Customer();
        $customer->name = 'AutoPay Test';
        $customer->autopay = false;
        $customer->saveOrFail();

        $operation = $this->getOperation($paymentSourceReconciler, $deletePaymentInfo);

        $operation->process(self::$merchantAccount, $customer, $reference);

        $this->assertTrue(true);
    }
}
