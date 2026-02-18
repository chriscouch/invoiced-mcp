<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Query;
use App\Integrations\Flywire\Models\FlywireRefund;

/**
 * @extends AbstractCsvExporter<FlywireRefund>
 */
class FlywireRefundExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(FlywireRefund::class, $this->company, $options);

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        return [
            'refund_id',
            'recipient_id',
            'initiated_at',
            'amount',
            'currency',
            'amount_to',
            'currency_to',
            'status',
        ];
    }

    public static function getId(): string
    {
        return 'flywire_refund_csv';
    }
}
