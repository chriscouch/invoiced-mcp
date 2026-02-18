<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Integrations\Flywire\Models\FlywireDisbursement;
use App\Integrations\Flywire\Models\FlywirePayout;
use App\Integrations\Flywire\Models\FlywireRefund;

/**
 * @extends AbstractCsvExporter<FlywireDisbursement>
 */
class FlywireDisbursementExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(FlywireDisbursement::class, $this->company, $options);

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        return [
            'disbursement_id',
            'recipient_id',
            'delivered_at',
            'status_text',
            'bank_account_number',
            'amount',
            'currency',
            'payment.payment_id',
            'payment.amount',
            'refund.refund_id',
            'refund.amount_to',
        ];
    }

    protected function getCsvModelItems(Model $model): ?array
    {
        $payouts = FlywirePayout::where('disbursement_id', $model)
            ->all()
            ->toArray();

        $refunds = FlywireRefund::where('disbursement_id', $model)
            ->all()
            ->toArray();

        // Ensure the disbursement is included even if it has no items
        return array_merge($payouts, $refunds) ?: null;
    }

    protected function isLineItemColumn(string $column): bool
    {
        return str_starts_with($column, 'payment.') || str_starts_with($column, 'refund.');
    }

    protected function getCsvLineItemColumnValue(Model $model, string $field, mixed $item): string
    {
        if ($item instanceof FlywirePayout && str_starts_with($field, 'payment.')) {
            if ('payment.payment_id' == $field) {
                return $item->payment->payment_id;
            }

            $field = str_replace('payment.', '', $field);

            return $this->getCsvColumnValue($item, $field);
        }

        if ($item instanceof FlywireRefund && str_starts_with($field, 'refund.')) {
            $field = str_replace('refund.', '', $field);

            return $this->getCsvColumnValue($item, $field);
        }

        return '';
    }

    public static function getId(): string
    {
        return 'flywire_disbursement_csv';
    }
}
