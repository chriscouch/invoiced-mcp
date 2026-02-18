<?php

namespace App\Tests\Integrations\Adyen\Webhooks;

use App\Integrations\Adyen\EventSubscriber\AdyenPayoutSubscriber;
use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\Integrations\Adyen\ValueObjects\AdyenPlatformWebhookEvent;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\Tests\AppTestCase;
use Mockery;

class PayoutWebhookTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasMerchantAccount(AdyenGateway::ID, '1-frazlqhuji');
    }

    private function getHandler(): AdyenPayoutSubscriber
    {
        $createPayout = Mockery::mock(SaveAdyenPayout::class);
        $createPayout->shouldReceive('save')
            ->andReturn(null)
            ->once();

        return new AdyenPayoutSubscriber($createPayout);
    }

    public function testPerform(): void
    {
        $handler = $this->getHandler();
        $data = json_decode((string) file_get_contents(dirname(__DIR__).'/data/balancePlatform.payout.created.json'), true);
        $event = new AdyenPlatformWebhookEvent($data);
        $handler->process($event);
    }
}
