<?php

namespace App\CashApplication\ListQueryBuilders;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\ListQueryBuilders\AbstractListQueryBuilder;
use App\Core\Orm\Query;

/**
 * @extends AbstractListQueryBuilder<Transaction>
 */
class TransactionListQueryBuilder extends AbstractListQueryBuilder
{
    protected function fixLegacyOptions(ListFilter $filter): ListFilter
    {
        $filter = parent::fixLegacyOptions($filter);

        return $this->fixLegacyNumericJson($filter, 'amount');
    }

    public function initialize(): void
    {
        $this->query = Transaction::queryWithTenant($this->company)
            ->with('customer');

        $this->addFilters();
    }

    public static function getClassString(): string
    {
        return Transaction::class;
    }

    /**
     * Transactions do not have automations.
     */
    public function applyAutomation(Query $query): void
    {
    }
}
