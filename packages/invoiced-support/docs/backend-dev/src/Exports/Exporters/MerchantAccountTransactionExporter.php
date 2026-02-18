<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\PaymentProcessing\Models\MerchantAccountTransaction;

/**
 * @extends AbstractCsvExporter<MerchantAccountTransaction>
 */
class MerchantAccountTransactionExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(MerchantAccountTransaction::class, $this->company, $options);
        $listQueryBuilder->setSort('available_on DESC');

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        return [
            'reference',
            'type',
            'description',
            'available_on',
            'currency',
            'amount',
            'fee',
            'net',
            'source_type',
            'source_id',
            'payout.reference',
        ];
    }

    public static function getId(): string
    {
        return 'merchant_account_transaction_csv';
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if (str_starts_with($field, 'payout.') && $model instanceof MerchantAccountTransaction) {
            $payout = $model->payout;
            $field = str_replace('payout.', '', $field);

            return $payout ? $this->getCsvColumnValue($payout, $field) : '';
        }

        return parent::getCsvColumnValue($model, $field);
    }
}
