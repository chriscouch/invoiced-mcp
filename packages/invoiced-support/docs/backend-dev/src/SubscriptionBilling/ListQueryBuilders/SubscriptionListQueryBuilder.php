<?php

namespace App\SubscriptionBilling\ListQueryBuilders;

use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\ListQueryBuilders\AbstractListQueryBuilder;
use App\SubscriptionBilling\Models\Subscription;

/**
 * @extends AbstractListQueryBuilder<Subscription>
 */
class SubscriptionListQueryBuilder extends AbstractListQueryBuilder
{
    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        $filter = parent::fixLegacyOptions($filter);

        if (!array_value($this->options, 'all')) {
            // filter canceled subscriptions
            $filter = $filter->with(new FilterCondition(FilterOperator::Equal, 'canceled', (bool) array_value($this->options, 'canceled')));
            $filter = $filter->with(new FilterCondition(FilterOperator::Equal, 'finished', (bool) array_value($this->options, 'finished')));
        }

        $hasContract = array_value($this->options, 'contract');
        if ('0' === $hasContract) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Empty, 'cycles', null));
        } elseif ($hasContract) {
            $filter = $filter->with(new FilterCondition(FilterOperator::GreaterThan, 'cycles', 0));
        }

        return $filter;
    }

    public function initialize(): void
    {
        $this->query = Subscription::queryWithTenant($this->company)
            ->with('customer');

        $this->addFilters();

        // filter by plan
        if ($plan = array_value($this->options, 'plan')) {
            $quotedPlan = $this->database->quote($plan);
            $this->query->where('(plan='.$quotedPlan.' OR EXISTS (SELECT 1 FROM SubscriptionAddons WHERE subscription_id=Subscriptions.id AND plan='.$quotedPlan.'))');
        }
    }

    public static function getClassString(): string
    {
        return Subscription::class;
    }
}
