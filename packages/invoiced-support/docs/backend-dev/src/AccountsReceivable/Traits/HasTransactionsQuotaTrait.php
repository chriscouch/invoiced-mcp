<?php

namespace App\AccountsReceivable\Traits;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Core\Entitlements\Enums\QuotaType;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\ModelCreating;
use App\Core\Orm\Exception\ListenerException;

trait HasTransactionsQuotaTrait
{
    protected function autoInitializeTransactionQuota(): void
    {
        self::creating([static::class, 'checkTransactionQuota']);
    }

    public static function checkTransactionQuota(ModelCreating $event): void
    {
        /** @var static $model */
        $model = $event->getModel();
        $quota = $model->tenant()->quota->get(QuotaType::TransactionsPerDay);
        if (null === $quota) {
            return;
        }

        if ($model->getTransactionCountToday() >= $quota) {
            throw new ListenerException('The '.$quota.' transaction per day quota has been reached. Please upgrade your account or try again tomorrow to create additional transactions.');
        }
    }

    /**
     * Gets the number of transactions that have been
     * created in the current day by this tenant.
     */
    public function getTransactionCountToday(): int
    {
        $startOfDay = CarbonImmutable::now()->startOfDay();
        $transactionModels = [
            Invoice::class,
            Estimate::class,
            CreditNote::class,
        ];

        $count = 0;
        foreach ($transactionModels as $model) {
            $count += $model::where('created_at', $startOfDay->toDateTimeString(), '>=')->count();
        }

        return $count;
    }
}
