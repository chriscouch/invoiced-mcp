<?php

namespace App\EntryPoint\CronJob;

use App\Core\Mailer\Mailer;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\MerchantAccountTransactionNotification;
use Carbon\CarbonImmutable;

class MerchantTransactionsNotSettledNotificationCronJob extends AbstractTaskQueueCronJob
{
    public function __construct(
        private Mailer $mailer,
        private TenantContext $tenant,
    ) {
    }

    public function getTasks(): iterable
    {
        return MerchantAccountTransactionNotification::queryWithoutMultitenancyUnsafe()
            ->where('notified_on', null)
            ->where('created_at', CarbonImmutable::now()->subDays(10)->toDateTimeString(), '<=')
            ->all();
    }

    /** @param MerchantAccountTransactionNotification $task */
    public function runTask(mixed $task): bool
    {
        $company = $task->tenant();

        $this->tenant->runAs($company, function () use ($company, $task) {
            $paymentTransaction = MerchantAccountTransaction::queryWithCurrentTenant()
                ->where('source_id', $task->merchant_account_transaction->source_id)
                ->where('source_type', $task->merchant_account_transaction->source_type)
                ->where('type', MerchantAccountTransactionType::Payment)
                ->oneOrNull();

            if (!$paymentTransaction) {
                /** @var ?Charge $charge */
                $charge = Charge::find($task->merchant_account_transaction->source_id);

                //Send slack notification
                $this->mailer->send([
                    'from_email' => 'no-reply@invoiced.com',
                    'to' => [['email' => 'b2b-payfac-notificati-aaaaqfagorxgbzwrnrb7unxgrq@flywire.slack.com', 'name' => 'Payment Reversal Row from report']],
                    'subject' => "Payout Reconciliation - SentForSettle not set after 10 days- {$company->name}",
                    'text' => "Payout Reconciliation - SentForSettle not set after 10 days\nTenant ID: {$task->merchant_account_transaction->tenant_id}\nPSP Reference: {$charge?->gateway_id}",
                ]);
            }
        });

        return true;
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }
}