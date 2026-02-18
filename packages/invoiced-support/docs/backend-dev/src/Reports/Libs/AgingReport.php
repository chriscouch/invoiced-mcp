<?php

namespace App\Reports\Libs;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Traits\MemberAwareDashboardTrait;
use App\Reports\ValueObjects\AgingBreakdown;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

/**
 * Builds aging reports.
 */
class AgingReport
{
    use MemberAwareDashboardTrait;

    private CarbonImmutable $date;

    public function __construct(private AgingBreakdown $agingBreakdown, Company $company, private Connection $database)
    {
        $this->company = $company;
        $this->date = CarbonImmutable::now();
    }

    public function setDate(CarbonImmutable $date): void
    {
        $this->date = $date;
    }

    /**
     * Gets a company-wide aging breakdown.
     *
     * @return array [[amount => Money(), count => N], ...]
     */
    public function buildForCompany(string $currency): array
    {
        // build up the aging select query for both balance and # invoices
        $select = $this->getSelectColumns();
        $select[] = 'currency';

        $sql = 'SELECT '.implode(',', $select);

        $where = $this->getWhereConditions();

        $params = [];
        $params['currency'] = $currency;
        $where[] = 'currency=:currency';

        $sql .= ' FROM ('.$this->buildUnionQuery($where).') data';
        $sql .= ' GROUP BY currency';

        // execute the sql query
        $queryResult = $this->database->fetchAllAssociative($sql, $params);

        // parse the aging out of the query result
        // NOTE: simulating fetching for a single customer to maximize code reuse
        $queryResultByCustomer = [];
        if (count($queryResult) > 0) {
            $queryResultByCustomer[-1] = $queryResult[0];
        }

        return $this->buildAgingBuckets($currency, $queryResultByCustomer, -1);
    }

    /**
     * Gets the aging breakdown for a list of customers.
     *
     * @return array [customerId => [[amount => Money(), count => N], ...]]
     */
    public function buildForCustomers(array $customerIds, ?string $currency = null): array
    {
        // build up the aging select query for both balance and # invoices
        $select = $this->getSelectColumns();
        $select[] = 'customer';
        $select[] = 'currency';

        $sql = 'SELECT '.implode(',', $select);

        $where = $this->getWhereConditions();
        if (count($customerIds) > 0) {
            $where[] = 'customer in ('.implode(',', $customerIds).')';
        }

        $params = [];

        if ($currency) {
            $params['currency'] = $currency;
            $where[] = 'currency=:currency';
        }

        $sql .= ' FROM ('.$this->buildUnionQuery($where).') data';
        $sql .= ' GROUP BY customer,currency';

        // execute the sql query
        $queryResult = $this->database->fetchAllAssociative($sql, $params);

        // parse the aging out of the query result
        $queryResultByCustomer = [];
        foreach ($queryResult as $row) {
            $customerId = (int) $row['customer'];
            if (!in_array($customerId, $customerIds)) {
                $customerIds[] = $customerId;
            }

            // TODO: This could better handle conflicts when a customer
            // has invoices in multiple currencies. Currently the currency
            // that appears first will be selected which is unpredictable.
            if (!isset($queryResultByCustomer[$customerId])) {
                $queryResultByCustomer[$customerId] = $row;
            }
        }

        $result = [];
        foreach ($customerIds as $customerId) {
            $result[$customerId] = $this->buildAgingBuckets($currency, $queryResultByCustomer, $customerId);
        }

        return $result;
    }

    /**
     * Gets the aging breakdown for a specific customer.
     *
     * @return array [customerId => [[amount => Money(), count => N], ...]]
     */
    public function buildForCustomer(int $customerId, ?string $currency = null): array
    {
        return $this->buildForCustomers([$customerId], $currency);
    }

    //
    // Helpers
    //

    /**
     * Gets the select columns for the aging buckets.
     */
    public function getSelectColumns(): array
    {
        $select = [];
        $agingBuckets = $this->agingBreakdown->getBuckets();
        $dateColumn = $this->agingBreakdown->dateColumn;
        $ageFormula = 'FLOOR(('.time().' - `'.$dateColumn.'`) / 86400)';
        foreach ($agingBuckets as $i => $bucket) {
            $k = "age$i";
            if (-1 == $bucket['lower']) {
                $select[] = 'SUM(CASE WHEN '.$ageFormula.' <= -1 OR `'.$dateColumn.'` IS NULL'.
                    ' THEN balance ELSE 0 END) AS "'.$k.'"';
                $select[] = 'SUM(CASE WHEN '.$ageFormula.' <= -1 OR `'.$dateColumn.'` IS NULL'.
                    ' THEN 1 ELSE 0 END) AS "'.$k.'_count"';
            } elseif ($i == count($agingBuckets) - 1) {
                $select[] = 'SUM(CASE WHEN '.$ageFormula.' >= '.$bucket['lower'].
                    ' THEN balance ELSE 0 END) AS "'.$k.'"';
                $select[] = 'SUM(CASE WHEN '.$ageFormula.' >= '.$bucket['lower'].
                    ' THEN 1 ELSE 0 END) AS "'.$k.'_count"';
            } else {
                $select[] = 'SUM(CASE WHEN '.$ageFormula.' BETWEEN '.$bucket['lower'].' AND '.$bucket['upper'].
                    ' THEN balance ELSE 0 END) AS "'.$k.'"';
                $select[] = 'SUM(CASE WHEN '.$ageFormula.' BETWEEN '.$bucket['lower'].' AND '.$bucket['upper'].
                    ' THEN 1 ELSE 0 END) AS "'.$k.'_count"';
            }
        }

        return $select;
    }

    /**
     * Gets the where conditions for an aging query.
     */
    public function getWhereConditions(): array
    {
        $conditions = [
            'tenant_id='.$this->company->id(),
            'draft=0',
            'closed=0',
            'voided=0',
            'paid=0',
            '`date` <= '.$this->date->getTimestamp(),
        ];

        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Invoices.customer')) {
                    $conditions[] = $restriction;
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $conditions[] = 'customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')';
            }
        }

        return $conditions;
    }

    private function buildUnionQuery(array $where): string
    {
        $subQuery1 = 'SELECT customer,currency,balance,date,due_date FROM Invoices';
        $subQuery1 .= ' WHERE '.implode(' AND ', $where);
        $subQuery2 = 'SELECT customer,currency,-balance,date,NULL AS due_date FROM CreditNotes';
        $subQuery2 .= ' WHERE '.str_replace('Invoices.customer', 'CreditNotes.customer', implode(' AND ', $where));

        return $subQuery1.' UNION ALL '.$subQuery2;
    }

    /**
     * Builds the aging buckets for a given customer
     * given the aging result set.
     */
    public function buildAgingBuckets(?string $currency, array $queryResultByCustomer, int $customerId): array
    {
        $agingBuckets = $this->agingBreakdown->getBuckets();

        if (isset($queryResultByCustomer[$customerId])) {
            $row = $queryResultByCustomer[$customerId];
        } else {
            $row = false;
        }

        $result = [];
        foreach ($agingBuckets as $i => $bucket) {
            if ($row) {
                $k = "age$i";
                $result[] = [
                    'amount' => Money::fromDecimal($row['currency'], $row[$k]),
                    'count' => (int) $row[$k.'_count'],
                ];
            } else {
                $result[] = [
                    'amount' => new Money($currency ?? $this->company->currency, 0),
                    'count' => 0,
                ];
            }
        }

        return $result;
    }
}
