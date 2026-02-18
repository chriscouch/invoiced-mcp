<?php

namespace App\EntryPoint\QueueJob;

use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\AccountsReceivable\Models\Invoice;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\BillSubscription;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Lock\LockFactory;

/**
 * This queue job will bill up to 1,000 open subscriptions per company.
 */
class BillSubscriptionsJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const BATCH_SIZE = 1000;
    private const MAX_CYCLES = 100;

    public function __construct(private LockFactory $lockFactory, private BillSubscription $billSubscription)
    {
    }

    public function perform(): void
    {
        // only proceed if we can obtain the lock
        $lock = $this->lockFactory->createLock('bill_subscriptions:'.$this->args['tenant_id'], 7200); // 2 hours
        if (!$lock->acquire()) {
            return;
        }

        // NOTE: This does have a flaw where if there are
        // more than 1,000 subscriptions that fail, it will
        // not be possible for subscription 1,001 and onward
        // to be attempted until the first 1,000 subscriptions
        // are successfully billed. It is an edge case not worth
        // solving for right now but could cause future issues.
        foreach ($this->getSubscriptions() as $subscription) {
            try {
                // Keep billing this subscription, up to MAX_CYCLES,
                // until it no longer produces new invoices. This
                // will catch it up to the current point in time.
                $counter = 0;
                $continue = true;
                while ($counter < self::MAX_CYCLES && $continue) {
                    $invoice = $this->billSubscription->bill($subscription, true);
                    $continue = $invoice instanceof Invoice;
                    ++$counter;
                }
            } catch (OperationException $e) {
                // do nothing and keep processing other subscriptions
                $this->logger->info('Billing subscription failed', ['exception' => $e]);
            }
        }
    }

    /**
     * Gets all subscriptions that need to be billed.
     */
    public function getSubscriptions(): array
    {
        return Subscription::where('canceled', false)
            ->where('finished', false)
            ->where('pending_renewal', false)
            ->where('paused', false)
            ->where('renews_next IS NOT NULL')
            ->where('renews_next', CarbonImmutable::now()->getTimestamp(), '<=')
            ->sort('renews_next ASC,id ASC')
            ->first(self::BATCH_SIZE);
    }
}
