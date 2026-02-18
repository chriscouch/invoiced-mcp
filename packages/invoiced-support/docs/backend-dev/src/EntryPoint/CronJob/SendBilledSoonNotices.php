<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Sending\Email\Libs\EmailSpool;
use App\Sending\Email\Models\EmailTemplate;
use App\Sending\Email\Models\EmailTemplateOption;
use App\SubscriptionBilling\Libs\BilledSoonNotifier;
use Symfony\Component\Lock\LockFactory;

class SendBilledSoonNotices extends AbstractTaskQueueCronJob
{
    public function __construct(
        private TenantContext $tenant,
        private EmailSpool $emailSpool,
        private LockFactory $lockFactory
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        // Gets all of the days_before_renewal email template options
        // that have a value greater than 0 days
        return EmailTemplateOption::queryWithoutMultitenancyUnsafe()
            ->where('template', EmailTemplate::SUBSCRIPTION_BILLED_SOON)
            ->where('option', EmailTemplateOption::DAYS_BEFORE_BILLING)
            ->where('value', '0', '>')
            ->all();
    }

    /**
     * @param EmailTemplateOption $task
     */
    public function runTask(mixed $task): bool
    {
        /** @var Company $company */
        $company = $task->tenant();

        // check if the company is in good standing
        if (!$company->billingStatus()->isActive()) {
            return false;
        }

        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($company);

        $this->send($company, (int) $task->value);

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }

    /**
     * Sends out billed soon notifications to all subscriptions
     * matched within the time window.
     *
     * @return int # of billed soon notifications sent
     */
    public function send(Company $company, int $days): int
    {
        // Ensure date calculations are in company tz.
        $company->useTimezone();

        $notifier = new BilledSoonNotifier($company, $days);
        $subscriptions = $notifier->getSubscriptions();

        $this->statsd->gauge('cron.task_queue_size', count($subscriptions), 1, ['cron_job' => static::getName()]);

        $n = 0;
        foreach ($subscriptions as $subscription) {
            // check if a notice has been sent in last 24 hours
            // by grabbing a lock that is not released
            $lock = $this->lockFactory->createLock('billed_soon_notice:'.$subscription->id, 86400, false);
            if (!$lock->acquire()) {
                continue;
            }

            // if an error happens in this job we ignore it
            // because it will be retried in the next job
            // and depending on the error might already be logged
            $emailTemplate = EmailTemplate::make($subscription->tenant_id, EmailTemplate::SUBSCRIPTION_BILLED_SOON);
            $this->emailSpool->spoolDocument($subscription, $emailTemplate, [], false);
            ++$n;
        }

        $this->statsd->updateStats('cron.processed_task', $n, 1.0, ['cron_job' => static::getName()]);

        return $n;
    }
}
