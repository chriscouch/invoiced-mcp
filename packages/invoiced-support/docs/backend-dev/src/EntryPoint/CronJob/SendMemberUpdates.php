<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\Multitenant\TenantContext;
use App\Core\Utils\ModelUtility;
use App\Reports\Dashboard\EmailMemberUpdate;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Sends an email report to all companies that need it.
 */
abstract class SendMemberUpdates extends AbstractTaskQueueCronJob implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private TenantContext $tenant,
        private EmailMemberUpdate $emailUpdate,
    ) {
    }

    abstract public static function getFrequency(): string;

    public static function getName(): string
    {
        return 'member_updates_'.static::getFrequency();
    }

    public static function getLockTtl(): int
    {
        return 43200; // 12 hours
    }

    public function getTasks(): iterable
    {
        // only send updates to active companies
        return Company::where('canceled', false)
            ->all();
    }

    /**
     * @param Company $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task);

        $frequency = static::getFrequency();
        $query = Member::where('email_update_frequency', $frequency)
            ->where('last_accessed IS NOT NULL')
            ->where('expires', 0)
            ->with('user_id');
        $members = ModelUtility::getAllModelsGenerator($query);

        try {
            /**
             * @var Member $member
             */
            foreach ($members as $member) {
                $this->emailUpdate->setContext($task, $member, $frequency);
                $this->emailUpdate->send();
            }
        } catch (Throwable $e) {
            // do not let a single company failing stop the job
            $this->logger->error('Could not send member update email', ['exception' => $e]);
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return true;
    }
}
