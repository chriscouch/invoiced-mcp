<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

class CustomerPortalEvents implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private Connection $database)
    {
    }

    /**
     * Tracks an event that happened in the customer portal.
     */
    public function track(Customer $customer, CustomerPortalEvent $event): void
    {
        try {
            $this->database->insert('CustomerPortalEvents', [
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id(),
                'timestamp' => CarbonImmutable::now()->toDateTimeString(),
                'event' => $event->value,
            ]);
        } catch (Throwable $e) {
            // Any failures here should not halt execution. Log and move on.
            $this->logger->error('Could not save customer portal event', ['exception' => $e]);
        }
    }
}
