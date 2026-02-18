<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Query;
use App\PaymentProcessing\Models\Payout;

/**
 * @extends AbstractCsvExporter<Payout>
 */
class PayoutExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(Payout::class, $this->company, $options);
        $listQueryBuilder->setSort('initiated_at DESC');

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        return [
            'reference',
            'description',
            'initiated_at',
            'currency',
            'gross_amount',
            'pending_amount',
            'amount',
            'status',
            'bank_account_name',
            'statement_descriptor',
            'arrival_date',
            'failure_message',
        ];
    }

    public static function getId(): string
    {
        return 'payout_csv';
    }
}
