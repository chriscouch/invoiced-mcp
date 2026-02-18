<?php

namespace App\AccountsReceivable\ListQueryBuilders;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\ListQueryBuilders\AbstractListQueryBuilder;

/**
 * @extends AbstractListQueryBuilder<Customer>
 */
class CustomerListQueryBuilder extends AbstractListQueryBuilder
{
    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        $owner = (int) array_value($this->options, 'owner');
        if (-1 == $owner) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Empty, 'owner_id', null));
        } elseif ($owner > 0) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Equal, 'owner_id', $owner));
        }

        // payment source
        $hasPaymentSource = array_value($this->options, 'payment_source');
        if ($hasPaymentSource) {
            $filter = $filter->with(new FilterCondition(FilterOperator::NotEmpty, 'default_source_id', null));
        } elseif (false === $hasPaymentSource) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Empty, 'default_source_id', null));
        }

        // credit balance
        $hasCreditBalance = array_value($this->options, 'balance');
        if (false === $hasCreditBalance) {
            $filter = $filter->with(new FilterCondition(FilterOperator::Equal, 'credit_balance', 0));
        } elseif ($hasCreditBalance) {
            $filter = $filter->with(new FilterCondition(FilterOperator::GreaterThan, 'credit_balance', 0));
        }

        return $filter;
    }

    public function initialize(): void
    {
        $this->query = Customer::queryWithTenant($this->company);
        $this->query->with('chasing_cadence_id')
            ->with('next_chase_step_id');

        $this->addFilters();

        $hasBalance = array_value($this->options, 'open_balance');
        // balance
        if ($hasBalance) {
            $this->query->where('EXISTS (SELECT 1 FROM Invoices WHERE customer=Customers.id AND draft=0 AND closed=0 AND voided=0 and paid=0 AND date <= UNIX_TIMESTAMP())');
        } elseif (false === $hasBalance) {
            $this->query->where('NOT EXISTS (SELECT 1 FROM Invoices WHERE customer=Customers.id AND draft=0 AND closed=0 AND voided=0 and paid=0 AND date <= UNIX_TIMESTAMP())');
        }
    }

    public static function getClassString(): string
    {
        return Customer::class;
    }
}
