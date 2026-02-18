<?php

namespace App\EntryPoint\CronJob;

use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\Models\AdyenAccount;
use Carbon\CarbonImmutable;

class SendFlywirePaymentOnboardingReminders extends AbstractTaskQueueCronJob
{
    public function __construct(
        private Mailer $mailer,
        private TenantContext $tenant,
    ) {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return AdyenAccount::queryWithoutMultitenancyUnsafe()
            ->where('has_onboarding_problem', true)
            ->where('account_holder_id', null, '<>')
            ->where('onboarding_started_at', CarbonImmutable::now()->subDay()->toDateTimeString(), '<=')
            ->where('(last_onboarding_reminder_sent <= "'.CarbonImmutable::now()->subDays(7)->toDateString().'" OR last_onboarding_reminder_sent IS NULL)')
            ->all();
    }

    /**
     * @param AdyenAccount $task
     */
    public function runTask(mixed $task): bool
    {
        // Send the "Action Required" lifecycle email
        $company = $task->tenant();

        $this->tenant->runAs($company, function () use ($company, $task) {
            $this->mailer->sendToAdministrators(
                $company,
                [
                    'subject' => '⚡ Action Needed: Finish Your Flywire Payments Setup',
                    'reply_to_email' => 'support@invoiced.com',
                    'reply_to_name' => 'Invoiced Support',
                ],
                'flywire-payments-action-required',
                [
                    'name' => $company->name,
                ],
            );

            // Update the last reminder sent
            $task->last_onboarding_reminder_sent = CarbonImmutable::now();
            $task->saveOrFail();
        });

        return true;
    }
}
