<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Query;
use App\Integrations\Flywire\Models\FlywirePayment;

/**
 * @extends AbstractCsvExporter<FlywirePayment>
 */
class FlywirePaymentExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(FlywirePayment::class, $this->company, $options);

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        return [
            'payment_id',
            'recipient_id',
            'initiated_at',
            'amount_from',
            'amount_to',
            'currency_from',
            'currency_to',
            'status',
            'expiration_date',
            'payment_method_type',
            'payment_method_brand',
            'payment_method_card_classification',
            'payment_method_card_expiration',
            'payment_method_last4',
            'cancellation_reason',
            'reason',
            'reason_code',
        ];
    }

    public static function getId(): string
    {
        return 'flywire_payment_csv';
    }
}
