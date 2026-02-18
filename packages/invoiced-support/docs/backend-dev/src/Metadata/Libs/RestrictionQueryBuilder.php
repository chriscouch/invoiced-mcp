<?php

namespace App\Metadata\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Metadata\ValueObjects\CustomFieldRestriction;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use App\Core\Orm\Query;

class RestrictionQueryBuilder
{
    /**
     * @param CustomFieldRestriction[] $restrictions
     */
    public function __construct(private Company $company, private array $restrictions)
    {
    }

    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    /**
     * Adds restrictions to an ORM query builder.
     *
     * @param string $customerIdColumn The column that contains the customer ID, i.e. `Customers.id`, `customer`, or `id`
     */
    public function addToOrmQuery(string $customerIdColumn, Query $query): void
    {
        if ($sql = $this->buildSql($customerIdColumn)) {
            $query->where($sql);
        }
    }

    /**
     * Builds the SQL sub-query to add as a where condition
     * when restrictions are enabled.
     *
     * @param string $customerIdColumn The column that contains the customer ID, i.e. `Customers.id`, `customer`, or `id`
     */
    public function buildSql(string $customerIdColumn): ?string
    {
        if (0 == count($this->restrictions)) {
            return null;
        }

        $conditions = [];
        foreach ($this->restrictions as $restriction) {
            $conditions[] = new MetadataQueryCondition($restriction->getKey(), $restriction->getValues(), 'IN');
        }

        $model = new Customer();
        $storage = $model->getMetadataReader();
        $result = $storage->buildSqlConditions($conditions, $model, (int) $this->company->id(), $customerIdColumn);

        return '('.join(' OR ', $result).')';
    }
}
