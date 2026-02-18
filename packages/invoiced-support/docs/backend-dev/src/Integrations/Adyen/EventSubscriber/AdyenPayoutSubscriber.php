<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Integrations\Adyen\Operations\SaveAdyenPayout;
use App\Integrations\Adyen\ValueObjects\AdyenPlatformWebhookEvent;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenPayoutSubscriber implements EventSubscriberInterface
{
    private const array EVENT_TYPES = [
        'balancePlatform.transfer.created',
        'balancePlatform.transfer.updated',
    ];

    public function __construct(
        private SaveAdyenPayout $createPayout,
    ) {
    }

    public function process(AdyenPlatformWebhookEvent $event): void
    {
        if (!in_array($event->data['type'], self::EVENT_TYPES)) {
            return;
        }

        $transfer = $event->data['data'];
        if (!SaveAdyenPayout::isPayout($transfer)) {
            return;
        }

        $merchantAccount = MerchantAccount::where('gateway', AdyenGateway::ID)
            ->where('gateway_id', $transfer['balanceAccount']['reference'])
            ->oneOrNull();
        if (!$merchantAccount) {
            return;
        }

        $this->createPayout->save($transfer['id'], $merchantAccount);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenPlatformWebhookEvent::class => 'process',
        ];
    }
}
