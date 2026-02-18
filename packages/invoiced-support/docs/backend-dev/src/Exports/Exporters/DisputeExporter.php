<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Query;
use App\PaymentProcessing\Models\Dispute;

/**
 * @extends AbstractCsvExporter<Dispute>
 */
class DisputeExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(Dispute::class, $this->company, $options);

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        return [
            'created_at',
            'currency',
            'amount',
            'gateway',
            'gateway_id',
            'status',
            'reason',
            'defense_reason',
            'charge_id',
        ];
    }

    public static function getId(): string
    {
        return 'dispute_csv';
    }
}
