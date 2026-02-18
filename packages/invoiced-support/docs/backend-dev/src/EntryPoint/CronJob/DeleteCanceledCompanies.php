<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Libs\DeleteCompany;
use App\Companies\Models\CanceledCompany;
use App\Companies\Models\Company;
use App\Core\Cron\ValueObjects\Run;

/**
 * Deletes canceled companies older than N days
 * WARNING this function could be running for awhile.
 */
class DeleteCanceledCompanies extends AbstractTaskQueueCronJob
{
    const DELETE_CANCELED_COMPANIES_AFTER_N_DAYS = 90;

    private ?Run $run = null;

    public function __construct(private DeleteCompany $deleteCompany)
    {
    }

    public static function getLockTtl(): int
    {
        return 86400;
    }

    public function execute(Run $run): void
    {
        $this->run = $run;
        parent::execute($run);
    }

    public function getTasks(): iterable
    {
        // Selects canceled companies older than N days.
        $cutoff = time() - (self::DELETE_CANCELED_COMPANIES_AFTER_N_DAYS * 86400);

        return Company::where('canceled', true)
            ->where('canceled_at', $cutoff, '<=')
            ->all();
    }

    /**
     * @param Company $task
     */
    public function runTask(mixed $task): bool
    {
        if (!$this->recordAsCanceled($task)) {
            return false;
        }

        $this->deleteCompany->delete($task);
        if ($this->run) {
            $this->run->writeOutput('Deleted company # '.$task->id);
        }

        return true;
    }

    /**
     * Records a company as canceled before deleting it.
     */
    private function recordAsCanceled(Company $task): bool
    {
        if (CanceledCompany::find($task->id())) {
            return true;
        }

        $canceledCompany = new CanceledCompany();
        foreach (CanceledCompany::definition()->all() as $property) {
            $name = $property->name;
            if (isset($task->$name)) {
                $canceledCompany->$name = $task->$name;
            }
        }
        $canceledCompany->saveOrFail();

        return true;
    }
}
