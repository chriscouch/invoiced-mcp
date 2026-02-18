<?php

namespace App\Tests\PaymentProcessing\Libs;

use App\PaymentProcessing\Enums\PaymentFlowSource;
use App\PaymentProcessing\Enums\TokenizationFlowStatus;
use App\PaymentProcessing\Libs\TokenizationFlowManager;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\Tests\AppTestCase;

class TokenizationFlowManagerTest extends AppTestCase
{
    private static TokenizationFlow $tokenizationFlow;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    private function getManager(): TokenizationFlowManager
    {
        return self::getService('test.tokenization_flow_manager');
    }

    public function testCreate(): void
    {
        $manager = $this->getManager();

        self::$tokenizationFlow = new TokenizationFlow();
        self::$tokenizationFlow->initiated_from = PaymentFlowSource::Charge;
        $manager->create(self::$tokenizationFlow);

        $this->assertEquals(TokenizationFlowStatus::CollectPaymentDetails, self::$tokenizationFlow->status);
        $this->assertNull(self::$tokenizationFlow->completed_at);
        $this->assertNull(self::$tokenizationFlow->canceled_at);
    }

    public function testSetStatus(): void
    {
        $manager = $this->getManager();

        $manager->setStatus(self::$tokenizationFlow, TokenizationFlowStatus::ActionRequired);

        $manager->setStatus(self::$tokenizationFlow, TokenizationFlowStatus::Succeeded);
        $this->assertNotNull(self::$tokenizationFlow->completed_at);

        $manager->setStatus(self::$tokenizationFlow, TokenizationFlowStatus::Failed);
        $this->assertNotNull(self::$tokenizationFlow->completed_at);

        $manager->setStatus(self::$tokenizationFlow, TokenizationFlowStatus::Canceled);
        $this->assertNotNull(self::$tokenizationFlow->canceled_at);
    }
}
