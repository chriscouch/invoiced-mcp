<?php

namespace App\Integrations\Adyen\EventSubscriber;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\ValueObjects\AdyenPlatformWebhookEvent;
use Carbon\CarbonImmutable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdyenNegativeBalanceSubscriber implements EventSubscriberInterface
{
    private const array EVENT_TYPES = [
        'balancePlatform.negativeBalanceCompensationWarning.scheduled',
    ];

    public function __construct(
        private TenantContext $tenant,
        private Mailer $mailer,
        private bool $adyenLiveMode,
    ) {
    }

    public function process(AdyenPlatformWebhookEvent $event): void
    {
        if (!in_array($event->data['type'], self::EVENT_TYPES)) {
            return;
        }

        // Send a notification in live mode only
        if (!$this->adyenLiveMode) {
            return;
        }

        // If the tenant is not set then this means it was for an account holder not in our database.
        if (!$this->tenant->has()) {
            return;
        }

        $company = $this->tenant->get();

        // Report the warning to Slack
        $accountHolderId = $event->data['data']['accountHolder']['id'];
        $balanceAccountId = $event->data['data']['id'];
        $balance = new Money($event->data['data']['amount']['currency'], $event->data['data']['amount']['value']);
        $negativeSince = new CarbonImmutable($event->data['data']['negativeBalanceSince']);
        $scheduledCompensation = new CarbonImmutable($event->data['data']['scheduledCompensationAt']);
        $this->mailer->send([
            'from_email' => 'no-reply@invoiced.com',
            'to' => [['email' => 'b2b-payfac-notificati-aaaaqfagorxgbzwrnrb7unxgrq@flywire.slack.com', 'name' => 'Invoiced Payment Ops']],
            'subject' => "Negative Balance Compensation Warning - {$company->name}",
            'text' => "Adyen balance account has been negative for too long and is scheduled to be collected from Flywire.
Tenant ID: {$company->id}
Account Holder: $accountHolderId
Balance Account: $balanceAccountId
Balance: $balance
Negative since: {$negativeSince->format('n/d/Y')}
Scheduled compensation at: {$scheduledCompensation->format('n/d/Y')}",
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AdyenPlatformWebhookEvent::class => 'process',
        ];
    }
}
