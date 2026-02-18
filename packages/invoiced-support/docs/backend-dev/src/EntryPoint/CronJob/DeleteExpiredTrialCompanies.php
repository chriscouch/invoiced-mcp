<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Libs\DeleteCompany;
use App\Companies\Models\Company;
use App\Core\Cron\ValueObjects\Run;

/**
 * Deletes companies whose trial ended more than N days
 * that did not convert into paying customers.
 */
class DeleteExpiredTrialCompanies extends AbstractTaskQueueCronJob
{
    const DELETE_EXPIRED_TRIAL_COMPANIES_AFTER_N_DAYS = 90;

    private ?Run $run = null;

    public function __construct(private DeleteCompany $deleteCompany)
    {
    }

    public static function getName(): string
    {
        return 'delete_expired_trials';
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function execute(Run $run): void
    {
        $this->run = $run;
        parent::execute($run);
    }

    public function getTasks(): iterable
    {
        // Selects companies whose trial ended more than N days
        // that did not convert into paying customers.
        $cutoff = time() - (self::DELETE_EXPIRED_TRIAL_COMPANIES_AFTER_N_DAYS * 86400);

        return Company::where('trial_ends > 0')
            ->where('trial_ends', $cutoff, '<=')
            ->where('canceled', false)
            ->all();
    }

    /**
     * @param Company $task
     */
    public function runTask(mixed $task): bool
    {
        $this->deleteCompany->delete($task);
        if ($this->run) {
            $this->run->writeOutput('Deleted company # '.$task->id);
        }

        return true;
    }
}
