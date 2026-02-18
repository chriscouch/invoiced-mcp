<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\PaymentPlans\Models\PaymentPlanInstallment;

class PaymentPlanExporter extends InvoiceCsvExporter
{
    protected function getQuery(array $options): Query
    {
        return parent::getQuery($options)
            ->where('payment_plan_id IS NOT NULL');
    }

    protected function getColumns(): array
    {
        return [
            'customer.name',
            'customer.number',
            'number',
            'currency',
            'total',
            'balance',
            'autopay',
            'next_payment_attempt',
            'attempt_count',
            'payment_plan.status',
            'installment.date',
            'installment.amount',
            'installment.balance',
        ];
    }

    protected function getCsvColumnLabel(string $field): string
    {
        return match ($field) {
            'attempt_count' => 'payment_attempts',
            default => parent::getCsvColumnLabel($field),
        };
    }

    protected function getCsvModelItems(Model $model): ?array
    {
        return $model->paymentPlan()?->installments ?: [];
    }

    protected function isLineItemColumn(string $column): bool
    {
        return str_starts_with($column, 'installment.');
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        // installment
        if (str_starts_with($field, 'installment.') && $model instanceof PaymentPlanInstallment) {
            $field = str_replace('installment.', '', $field);
        }

        return parent::getCsvColumnValue($model, $field);
    }

    protected function getCsvLineItemColumnValue(Model $model, string $field, mixed $item): string
    {
        if ($item instanceof PaymentPlanInstallment) {
            return $this->getCsvColumnValue($item, $field);
        }

        return parent::getCsvLineItemColumnValue($model, $field, $item);
    }

    public static function getId(): string
    {
        return 'payment_plan_csv';
    }
}
