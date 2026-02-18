<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;
use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;

class SendTrialReminders implements CronJobInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    const SEND_REMINDER_DAYS_IN_ADVANCE = 3;

    public function __construct(
        private Mailer $mailer,
        private TenantContext $tenant,
        private string $dashboardUrl,
    ) {
    }

    public static function getName(): string
    {
        return 'send_trial_reminders';
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function execute(Run $run): void
    {
        [$m, $n] = $this->sendTrialReminders();

        $run->writeOutput("Sent $m trial ending soon notifications");
        $run->writeOutput("Sent $n trial ended notifications");
    }

    /**
     * Gets members with trials that are ending soon but not notified yet.
     */
    public function getTrialsEndingSoon(): iterable
    {
        // reminder window is valid for up to 1 day
        $end = time() + self::SEND_REMINDER_DAYS_IN_ADVANCE * 86400;
        $start = $end - 86400;

        return Company::where('canceled', false)
            ->where('trial_ends', $start, '>=')
            ->where('trial_ends', $end, '<=')
            ->where('last_trial_reminder IS NULL')
            ->all();
    }

    /**
     * Gets members with trials that have ended but not notified yet.
     */
    public function getEndedTrials(): iterable
    {
        return Company::where('canceled', false)
            ->where('trial_ends', 0, '>')
            ->where('trial_ends', time(), '<')
            ->where('(last_trial_reminder < trial_ends OR last_trial_reminder IS NULL)')
            ->all();
    }

    /**
     * Sends out trial reminders - trial_will_end and trial_ended.
     *
     * @return array [sent ending soon notices, sent ended notices]
     */
    public function sendTrialReminders(): array
    {
        return [
            self::sendTrialWillEndReminders(),
            self::sendTrialEndedReminders(),
        ];
    }

    private function sendTrialWillEndReminders(): int
    {
        /** @var Company[] $companies */
        $companies = $this->getTrialsEndingSoon();
        $n = 0;

        $this->statsd->gauge('cron.task_queue_size', count($companies), 1, ['cron_job' => static::getName()]);

        foreach ($companies as $company) {
            $this->tenant->runAs($company, function () use ($company) {
                $this->mailer->sendToAdministrators(
                    $company,
                    [
                        'subject' => 'Your Invoiced trial ends soon',
                    ],
                    'trial-will-end',
                    [
                        'company' => $company->name,
                        'dashboardUrl' => $this->dashboardUrl,
                    ],
                );

                $company->last_trial_reminder = time();
                $company->save();

                $this->statsd->increment('trial_funnel.trial_will_end_email');
            });

            ++$n;
        }

        $this->statsd->updateStats('cron.processed_task', $n, 1.0, ['cron_job' => static::getName()]);

        return $n;
    }

    private function sendTrialEndedReminders(): int
    {
        /** @var Company[] $companies */
        $companies = $this->getEndedTrials();
        $n = 0;
        foreach ($companies as $company) {
            $this->tenant->runAs($company, function () use ($company) {
                $this->mailer->sendToAdministrators(
                    $company,
                    [
                        'subject' => 'Your Invoiced trial has ended',
                    ],
                    'trial-ended',
                    [
                        'company' => $company->name,
                        'dashboardUrl' => $this->dashboardUrl,
                    ],
                );

                $company->last_trial_reminder = time();
                $company->save();

                $this->statsd->increment('trial_funnel.trial_ended_email');
            });

            ++$n;
        }

        return $n;
    }
}
