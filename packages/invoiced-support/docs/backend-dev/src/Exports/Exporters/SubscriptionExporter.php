<?php

namespace App\Exports\Exporters;

use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\SubscriptionBilling\Models\Subscription;

/**
 * @extends AbstractCsvExporter<Subscription>
 */
class SubscriptionExporter extends AbstractCsvExporter
{
    protected function getQuery(array $options): Query
    {
        $listQueryBuilder = $this->listQueryFactory->get(Subscription::class, $this->company, $options);
        $listQueryBuilder->setSort('date ASC');

        return $listQueryBuilder->getBuildQuery();
    }

    protected function getColumns(): array
    {
        $columns = [
            'customer.name',
            'customer.number',
            'customer.email',
            'customer.address1',
            'customer.address2',
            'customer.city',
            'customer.state',
            'customer.postal_code',
            'customer.country',
            'plan',
            'plan.interval_count',
            'plan.interval',
            'plan.currency',
            'recurring_total',
            'mrr',
            'quantity',
            'status',
            'start_date',
            'renewed_last',
            'renews_next',
            'period_start',
            'period_end',
            'cycles',
            'contract_renewal_mode',
            'contract_renewal_cycles',
            'contract_period_start',
            'contract_period_end',
            'cancel_at_period_end',
            'created_at',
            'canceled_at',
        ];

        $metadataColumns = $this->getMetadataColumns(ObjectType::Subscription);

        return array_merge($columns, $metadataColumns);
    }

    protected function getCsvColumnLabel(string $field): string
    {
        return match ($field) {
            'renewed_last' => 'last_bill',
            'renews_next' => 'next_bill',
            'period_start' => 'current_period_start',
            'period_end' => 'current_period_end',
            default => parent::getCsvColumnLabel($field),
        };
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if ($model instanceof Subscription) {
            // customer
            if (str_starts_with($field, 'customer.')) {
                $customer = $model->customer();
                $field = str_replace('customer.', '', $field);

                return $this->getCsvColumnValue($customer, $field);
            }

            // plan
            if (str_starts_with($field, 'plan.')) {
                $plan = $model->plan();
                $field = str_replace('plan.', '', $field);

                return $this->getCsvColumnValue($plan, $field);
            }
        }

        return parent::getCsvColumnValue($model, $field);
    }

    public static function getId(): string
    {
        return 'subscription_csv';
    }
}
