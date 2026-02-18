<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\PaymentFlowStatus;
use App\PaymentProcessing\Libs\PaymentFlowManager;
use App\PaymentProcessing\Models\PaymentFlow;
use App\Tests\AppTestCase;

class PaymentFlowManagerTest extends AppTestCase
{
    private static PaymentFlow $paymentFlow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getManager(): PaymentFlowManager
    {
        return self::getService('test.payment_flow_manager');
    }

    public function testCreate(): void
    {
        $manager = $this->getManager();

        self::$paymentFlow = new PaymentFlow();
        self::$paymentFlow->amount = 100;
        self::$paymentFlow->currency = 'usd';
        self::$paymentFlow->initiated_from = PaymentFlowSource::Charge;
        $manager->create(self::$paymentFlow);

        $this->assertEquals(PaymentFlowStatus::CollectPaymentDetails, self::$paymentFlow->status);
        $this->assertNull(self::$paymentFlow->processing_started_at);
        $this->assertNull(self::$paymentFlow->completed_at);
        $this->assertNull(self::$paymentFlow->canceled_at);
    }
}
