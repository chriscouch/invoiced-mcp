<?php

namespace App\Tests\PaymentProcessing\Models;

use App\CashApplication\Enums\PaymentItemIntType;
use App\Core\Orm\Model;
use App\Core\Utils\RandomString;
use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentFlowApplication;
use App\Tests\ModelTestCase;

/**
 * @extends ModelTestCase<PaymentFlow>
 */
class PaymentFlowTest extends ModelTestCase
{
    private static PaymentFlow $paymentFlow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCustomer();
    }

    protected function getModelCreate(): Model
    {
        $paymentFlow = new PaymentFlow();
        $paymentFlow->identifier = RandomString::generate();
        $paymentFlow->status = PaymentFlowStatus::CollectPaymentDetails;
        $paymentFlow->currency = 'usd';
        $paymentFlow->amount = 100;
        $paymentFlow->initiated_from = PaymentFlowSource::Api;
        self::$paymentFlow = $paymentFlow;

        return $paymentFlow;
    }

    public function testCreate(): void
    {
        parent::testCreate();
        $this->assertEquals(self::$company->id(), self::$paymentFlow->tenant_id);

        $item1 = new PaymentFlowApplication();
        $item1->payment_flow = self::$paymentFlow;
        $item1->type = PaymentItemIntType::Invoice;
        $item1->amount = 100;
        $item1->saveOrFail();

        $item2 = new PaymentFlowApplication();
        $item2->payment_flow = self::$paymentFlow;
        $item2->type = PaymentItemIntType::Invoice;
        $item2->amount = 200;
        $item2->saveOrFail();
    }

    /**
     * @depends testToArray
     * @depends testEdit
     */
    public function testUpdate(): void
    {
        $this->assertNull(self::$paymentFlow->completed_at);
        self::$paymentFlow->status = PaymentFlowStatus::Succeeded;
        self::$paymentFlow->save();
        self::$paymentFlow = PaymentFlow::findOrFail(self::$paymentFlow->id);
        $this->assertEquals(PaymentFlowStatus::Succeeded, self::$paymentFlow->status);
        $completedAt = self::$paymentFlow->completed_at;
        $this->assertNotNull($completedAt);

        self::$paymentFlow->status = PaymentFlowStatus::Processing;
        self::$paymentFlow->save();
        self::$paymentFlow = PaymentFlow::findOrFail(self::$paymentFlow->id);
        $this->assertEquals(PaymentFlowStatus::Succeeded, self::$paymentFlow->status);
        $this->assertEquals($completedAt, self::$paymentFlow->completed_at);

    }

    protected function getExpectedToArray($model, array &$output): array
    {
        return [
            'amount' => 100.0,
            'canceled_at' => null,
            'completed_at' => null,
            'created_at' => self::$paymentFlow->created_at,
            'currency' => 'usd',
            'customer_id' => null,
            'email' => null,
            'id' => self::$paymentFlow->id,
            'identifier' => self::$paymentFlow->identifier,
            'initiated_from' => 'api',
            'make_payment_source_default' => null,
            'payment_method' => null,
            'payment_link_id' => null,
            'payment_source_id' => null,
            'payment_source_type' => null,
            'payment_values' => (object) [],
            'processing_started_at' => null,
            'return_url' => null,
            'save_payment_source' => null,
            'status' => 'collect_payment_details',
            'updated_at' => self::$paymentFlow->updated_at,
            'country' => null,
            'expMonth' => null,
            'expYear' => null,
            'funding' => null,
            'gateway' => null,
            'last4' => null,
            'merchant_account_id' => null,
        ];
    }

    protected function getModelEdit($model): PaymentFlow
    {
        $model->amount = 200;

        return $model;
    }
}
