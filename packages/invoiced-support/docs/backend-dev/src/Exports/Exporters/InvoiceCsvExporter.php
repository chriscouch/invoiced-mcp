<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\Invoice;
use App\Core\Orm\Model;

/**
 * @extends DocumentCsvExporter<Invoice>
 */
class InvoiceCsvExporter extends DocumentCsvExporter
{
    protected function getColumnsDocument(): array
    {
        return [
            'number',
            'date',
            'due_date',
            'age',
            'status',
            'currency',
            'subtotal',
            'total',
            'balance',
            'payment_terms',
            'autopay',
            'next_payment_attempt',
            'created_at',
        ];
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if (str_starts_with($field, 'payment_plan.') && $model instanceof Invoice) {
            $paymentPlan = $model->paymentPlan();
            $field = str_replace('payment_plan.', '', $field);

            return $paymentPlan ? $this->getCsvColumnValue($paymentPlan, $field) : '';
        }

        return parent::getCsvColumnValue($model, $field);
    }

    public static function getId(): string
    {
        return 'invoice_csv';
    }

    public function getClass(): string
    {
        return Invoice::class;
    }
}
