<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Operations\EnableAdyenPayouts;
use App\Integrations\Adyen\ValueObjects\AdyenPlatformWebhookEvent;
use App\Integrations\Exceptions\IntegrationApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenAccountHolderSubscriber implements EventSubscriberInterface
{
    private const array EVENT_TYPES = [
        'balancePlatform.accountHolder.created',
        'balancePlatform.accountHolder.updated',
    ];

    public function __construct(
        private FlywirePaymentsOnboarding $onboarding,
        private EnableAdyenPayouts $enableAdyenPayouts,
        private TenantContext $tenant, private readonly AdyenClient $adyenClient,
    ) {
    }

    public function process(AdyenPlatformWebhookEvent $event): void
    {
        if (!in_array($event->data['type'], self::EVENT_TYPES)) {
            return;
        }

        // If the tenant is not set then this means it was for an account holder not in our database.
        if (!$this->tenant->has()) {
            return;
        }

        // An account holder updated webhook will only have a single capability in the body.
        // We should only perform actions based on the present of the capability.

        // Toggle ability to receive payments
        $adyenAccount = AdyenAccount::one();

        if ($this->isCapabilityIncluded($event, 'receivePayments')) {
            $enabled = $this->hasCapability($event, 'receivePayments');
            if ($enabled) {
                $this->onboarding->activateAccount($adyenAccount);
            } else {
                $this->onboarding->disablePayments();
            }
        }

        // Create a daily sweep if one does not exist
        if ($this->isCapabilityIncluded($event, 'sendToTransferInstrument')) {
            if ($this->hasCapability($event, 'sendToTransferInstrument')) {
                $this->enableAdyenPayouts->enableDailyPayouts($adyenAccount);
            }
        }

        // Update the account has onboarding problem to the latest value
        try {
            $accountHolder = $this->adyenClient->getAccountHolder((string) $adyenAccount->account_holder_id);

            $hasProblems = false;
            foreach ($accountHolder['capabilities'] as $capability) {
                if (count($capability['problems'] ?? []) > 0) {
                    $hasProblems = true;
                    break;
                }
            }
            if ($hasProblems != $adyenAccount->has_onboarding_problem) {
                $adyenAccount->has_onboarding_problem = $hasProblems;
                $adyenAccount->saveOrFail();
            }
        } catch (IntegrationApiException) {
            // ignore exceptions here
        }
    }

    private function isCapabilityIncluded(AdyenPlatformWebhookEvent $event, string $capability): bool
    {
        return isset($event->data['data']['accountHolder']['capabilities'][$capability]);
    }

    private function hasCapability(AdyenPlatformWebhookEvent $event, string $capability): bool
    {
        $capability = $event->data['data']['accountHolder']['capabilities'][$capability] ?? ['enabled' => false, 'allowed' => false];

        return $capability['enabled'] && $capability['allowed'];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenPlatformWebhookEvent::class => 'process',
        ];
    }
}
