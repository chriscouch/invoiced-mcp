<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * Cancels non-paid accounts which are inactive for a long period of time.
 */
class CancelInactiveAccounts extends AbstractTaskQueueCronJob
{
    public function __construct(private Connection $database)
    {
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function getTasks(): iterable
    {
        return array_merge(
            $this->getInactiveTestModeAccounts(),
            $this->getStaleNotActivatedAccounts(),
            $this->getStaleAbandonedSetups(),
        );
    }

    /**
     * @param int $task
     */
    public function runTask(mixed $task): bool
    {
        $company = Company::findOrFail($task);
        $this->cancelCompany($company);

        return true;
    }

    /**
     * Gets test mode companies which have never been logged into
     * or not logged into within the last 180 days.
     */
    private function getInactiveTestModeAccounts(): array
    {
        return $this->database->fetchFirstColumn('SELECT id,(SELECT MAX(FROM_UNIXTIME(last_accessed)) FROM Members WHERE tenant_id=Companies.id) AS last_activity FROM Companies WHERE canceled=0 AND test_mode=1 HAVING last_activity IS NULL OR last_activity <= :date', [
            'date' => CarbonImmutable::now()->subDays(180)->toDateString(),
        ]);
    }

    /**
     * Gets accounts which have the "not_activated" feature flag
     * that were created more than 90 days ago.
     */
    private function getStaleNotActivatedAccounts(): array
    {
        return $this->database->fetchFirstColumn("SELECT id FROM Companies WHERE canceled=0 AND EXISTS (SELECT 1 FROM Features WHERE tenant_id=Companies.id AND feature='not_activated' AND enabled=1) AND created_at <= :date", [
            'date' => CarbonImmutable::now()->subDays(90)->toDateString(),
        ]);
    }

    /**
     * Gets not billed accounts which have the not started onboarding
     * that were created more than 7 days ago.
     */
    private function getStaleAbandonedSetups(): array
    {
        return $this->database->fetchFirstColumn("SELECT c.id FROM Companies c JOIN BillingProfiles b ON b.id=c.billing_profile_id WHERE canceled=0 AND b.billing_system IS NULL AND c.name='' AND c.created_at <= :date", [
            'date' => CarbonImmutable::now()->subDays(7)->toDateString(),
        ]);
    }

    /**
     * Cancels a company. This intentionally does not cancel
     * the subscription in the billing system because this is
     * an unpaid account. We do not want to mistakenly cancel
     * a subscription when they may have other active accounts
     * which they pay for.
     */
    private function cancelCompany(Company $company): void
    {
        $company->canceled = true;
        $company->canceled_at = time();
        $company->canceled_reason = 'inactivity';
        $company->saveOrFail();
    }
}
