<?php

namespace App\EntryPoint\CronJob;

use App\Core\Authentication\Models\PersistentSession;
use App\Core\Authentication\Models\UserLink;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Database\DatabaseHelper;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * This job is responsible for deleting records in the database
 * which are older and no longer necessary to keep.
 */
class GarbageCollection implements CronJobInterface
{
    public function __construct(private Connection $database)
    {
    }

    public static function getName(): string
    {
        return 'garbage_collection';
    }

    public static function getLockTtl(): int
    {
        return 86400;
    }

    public function execute(Run $run): void
    {
        $run->writeOutput('Deleting account security events more than 3 years old');
        $this->deleted($this->accountSecurityEvents(), $run);
        $run->writeOutput('Deleting expired active sessions');
        $this->deleted($this->activeSessions(), $run);
        $run->writeOutput('Deleting expired api keys');
        $this->deleted($this->apiKeys(), $run);
        $run->writeOutput('Deleting customer portal events more than 3 years old');
        $this->deleted($this->customerPortalEvents(), $run);
        $run->writeOutput('Deleting expired customer portal sessions');
        $this->deleted($this->customerPortalSessions(), $run);
        $run->writeOutput('Deleting all account logging feature flags');
        $this->deleted($this->disableLogging(), $run);
        $run->writeOutput('Deleting events more than 7 years old');
        $this->deleted($this->events(), $run);
        $run->writeOutput('Deleting exports more than 7 days old');
        $this->deleted($this->exports(), $run);
        $run->writeOutput('Deleting imports more than 3 months old');
        $this->deleted($this->imports(), $run);
        $run->writeOutput('Deleting expired members');
        $this->deleted($this->members(), $run);
        $run->writeOutput('Deleting expired network invitations');
        $this->deleted($this->networkInvitations(), $run);
        $run->writeOutput('Deleting old queued network document sends');
        $this->deleted($this->networkPendingDocuments(), $run);
        $run->writeOutput('Deleting notification events older then 90 days');
        $this->deleted($this->notificationEvents(), $run);
        $run->writeOutput('Deleting expired persistent sessions');
        $this->deleted($this->persistentSessions(), $run);
        $run->writeOutput('Deleting reports more than 7 days old');
        $this->deleted($this->reports(), $run);
        $run->writeOutput('Deleting expired reset password links');
        $this->deleted($this->resetPasswordLinks(), $run);
        $run->writeOutput('Deleting textract imports older that 15 days');
        $this->deleted($this->textractImports(), $run);
        $run->writeOutput('Deleting webhook attempts more than 3 months old');
        $this->deleted($this->webhookAttempts(), $run);
    }

    private function deleted(int $n, Run $run): void
    {
        $run->writeOutput('Deleted '.$n.' row(s)');
    }

    /**
     * Deletes account security events more than 3 years old.
     */
    private function accountSecurityEvents(): int
    {
        $expires = (new CarbonImmutable('-3 years'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'AccountSecurityEvents', 'created_at < "'.$expires.'"', 1000, true);
    }

    /**
     * Deletes expired active sessions.
     */
    private function activeSessions(): int
    {
        $expires = (new CarbonImmutable('-1 hour'))->getTimestamp();

        return (int) $this->database->executeStatement('DELETE FROM ActiveSessions WHERE expires < ?', [$expires]);
    }

    /**
     * Deletes expired API keys.
     */
    private function apiKeys(): int
    {
        $expires = (new CarbonImmutable('-1 hour'))->getTimestamp();

        return DatabaseHelper::bigDelete($this->database, 'ApiKeys', 'expires IS NOT NULL AND expires < '.$expires);
    }

    /**
     * Deletes customer portal events more than 3 years old.
     */
    private function customerPortalEvents(): int
    {
        $expires = (new CarbonImmutable('-3 years'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'CustomerPortalEvents', 'timestamp < "'.$expires.'"', 1000, true);
    }

    /**
     * Deletes expired customer portal sessions.
     */
    private function customerPortalSessions(): int
    {
        $expires = CarbonImmutable::now()->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'CustomerPortalSessions', 'expires < "'.$expires.'"', 1000, true);
    }

    /**
     * Disables all logging feature flags. This is cleaned up
     * periodically because logging is usually only needed for
     * a short period of time but then the feature flag is left
     * on permanently. We don't want to always log account data
     * because it is a security risk to leave on, causes a
     * performance hit and is expensive to store.
     */
    private function disableLogging(): int
    {
        return DatabaseHelper::bigDelete($this->database, 'Features', 'feature like "log_%"');
    }

    /**
     * Deletes events more than 7 years old.
     */
    private function events(): int
    {
        $expires = (new CarbonImmutable('-7 years'))->getTimestamp();

        return DatabaseHelper::bigDelete($this->database, 'Events', 'timestamp < '.$expires, 1000, true);
    }

    /**
     * Deletes exports more than 7 days old.
     */
    private function exports(): int
    {
        $expires = (new CarbonImmutable('-7 days'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'Exports', 'created_at < "'.$expires.'"', 1000, true);
    }

    /**
     * Deletes imports more than 3 months old.
     */
    private function imports(): int
    {
        $expires = (new CarbonImmutable('-3 months'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'Imports', 'created_at < "'.$expires.'"', 1000, true);
    }

    /**
     * Deletes expired members.
     */
    private function members(): int
    {
        $expires = (new CarbonImmutable('-1 hour'))->getTimestamp();

        return DatabaseHelper::bigDelete($this->database, 'Members', 'expires > 0 AND expires < '.$expires);
    }

    /**
     * Deletes network invitations that have not been declined and have expired.
     */
    private function networkInvitations(): int
    {
        $expires = (new CarbonImmutable('-1 hour'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'NetworkInvitations', 'declined = 0 AND expires_at < "'.$expires.'"');
    }

    /**
     * Deletes queued network document sends that have been in the queue for more than a month.
     */
    private function networkPendingDocuments(): int
    {
        $expires = (new CarbonImmutable('-30 days'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'NetworkQueuedSends', 'created_at < "'.$expires.'"');
    }

    /**
     * Deletes notification events attempts more than 90 days old.
     */
    private function notificationEvents(): int
    {
        return DatabaseHelper::bigDelete($this->database, 'NotificationEvents', 'created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
    }

    /**
     * Deletes expired persistent sessions.
     */
    private function persistentSessions(): int
    {
        $expires = CarbonImmutable::createFromTimestamp(time() - PersistentSession::$sessionLength)->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'PersistentSessions', 'created_at < "'.$expires.'"');
    }

    /**
     * Deletes reports more than 7 days old.
     */
    private function reports(): int
    {
        $expires = (new CarbonImmutable('-7 days'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'Reports', 'created_at < "'.$expires.'"', 1000, true);
    }

    /**
     * Deletes expired reset password links.
     */
    private function resetPasswordLinks(): int
    {
        $expires = CarbonImmutable::createFromTimestamp(time() - UserLink::$forgotLinkTimeframe)->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'UserLinks', 'type="'.UserLink::FORGOT_PASSWORD.'" AND created_at < "'.$expires.'"');
    }

    /**
     * Deletes textract imports more than 14 days old.
     */
    private function textractImports(): int
    {
        $expires = (new CarbonImmutable('-14 days'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'TextractImports', 'created_at < "'.$expires.'"', 1000, true);
    }

    /**
     * Deletes webhook attempts more than 3 months old.
     */
    private function webhookAttempts(): int
    {
        $expires = (new CarbonImmutable('-3 months'))->toDateTimeString();

        return DatabaseHelper::bigDelete($this->database, 'WebhookAttempts', 'created_at < "'.$expires.'"', 1000, true);
    }
}
