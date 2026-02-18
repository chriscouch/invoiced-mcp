<?php

namespace App\Reports\DashboardMetrics;

use App\Companies\Models\Member;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class TopDebtorsMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'top_debtors';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return true;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->endOfDay();
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);

        $n = $options['count'] ?? 5;
        $currency = $options['currency'] ?? $context->company->currency;

        $restrictionsSQL = null;
        $restrictionsParameters = [];
        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('c.id')) {
                    $restrictionsSQL = $restriction;
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $restrictionsSQL = 'c.owner_id=?';
                $restrictionsParameters[] = $this->member->user_id;
            }
        }

        $date = CarbonImmutable::now()->endOfDay();
        $baseParameters = [$this->company->id(), $currency, $date->getTimestamp()];
        $params = array_merge($baseParameters, $baseParameters);
        $salesSubQuery = '(SELECT customer,balance,date,status FROM Invoices WHERE tenant_id=? AND currency=? AND draft=0 AND paid=0 AND closed=0 AND voided=0 AND date <= ?
            UNION ALL
            SELECT customer,-balance,date,status FROM CreditNotes WHERE tenant_id=? AND currency=? AND draft=0 AND paid=0 AND closed=0 AND voided=0 AND date <= ?) s';

        if ($restrictionsSQL) {
            // old way
            $sql = 'SELECT c.id as customer,c.name as customerName,SUM(balance) as balance,COUNT(*) as numInvoices,FLOOR(('.time().' - MIN(s.date)) / 86400) as age,MAX(IF(s.status="past_due", 1, 0)) as pastDue';
            $sql .= ' FROM '.$salesSubQuery;
            $sql .= ' JOIN Customers c ON s.customer=c.id';
            $sql .= ' WHERE '.$restrictionsSQL;
            $sql .= ' GROUP BY s.customer';
            $sql .= ' ORDER BY balance DESC,age DESC';
            $sql .= ' LIMIT '.$n;
            $params = array_merge($params, $restrictionsParameters);
        } else {
            // optimized way
            $sql = 'SELECT c.name as customerName, i.customer, i.balance, i.numInvoices, FLOOR(('.time().' - i.age) / 86400) as age, i.pastDue FROM 
                (SELECT s.customer,SUM(balance) as balance,COUNT(*) as numInvoices, MIN(s.date) as age, MAX(s.status="past_due") as pastDue
                FROM '.$salesSubQuery.'
                GROUP BY s.customer
                ORDER BY balance DESC,age DESC
                LIMIT '.$n.'
                ) as i
                JOIN Customers as c ON i.customer = c.id 
                ORDER BY balance DESC,age DESC';
        }

        $accounts = $this->database->fetchAllAssociative($sql, $params);

        foreach ($accounts as &$account) {
            $account['balance'] = (float) $account['balance'];
            $account['age'] = (int) $account['age'];
            $account['numInvoices'] = (int) $account['numInvoices'];
            $account['pastDue'] = (bool) $account['pastDue'];
        }

        return [
            'top_debtors' => $accounts,
        ];
    }
}
