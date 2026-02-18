<?php

namespace App\Reports\Dashboard;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\I18n\Currencies;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Reports\Libs\AgingReport;
use App\Reports\Libs\ReportHelper;
use App\Reports\Traits\MemberAwareDashboardTrait;
use App\Reports\ValueObjects\AgingBreakdown;
use App\SubscriptionBilling\ValueObjects\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @deprecated use dashboard metrics instead
 */
class Dashboard implements StatsdAwareInterface
{
    use StatsdAwareTrait;
    use MemberAwareDashboardTrait;

    const NUM_OUTSTANDING = 5;

    private ?Customer $customer = null;

    public function __construct(
        private Connection $database,
        private CacheInterface $cache,
        ReportHelper $helper
    ) {
        $this->helper = $helper;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    /**
     * Compiles all the necessary data to show a dashboard.
     */
    public function generate(?string $currency = null): array
    {
        $start = microtime(true);
        $currency = $this->determineCurrency($currency);

        // cache the entire dashboard based on the last event
        $lastEventId = $this->getLastEventId();
        $metric = 'build_'.$currency.'_'.$lastEventId;
        $key = $this->getCacheKey($metric);

        $result = $this->cache->get($key, function (ItemInterface $item) use ($currency) {
            // cache the generated result for a day
            $item->expiresAfter(86400);

            return $this->build($currency);
        });

        // time the build time in statsd
        $time = round((microtime(true) - $start) * 1000);
        $this->statsd->timing('report.dashboard.build_time', $time);

        return $result;
    }

    private function determineCurrency(?string $currency): string
    {
        if (!$currency) {
            // use customer's primary currency when appropriate
            if ($this->customer) {
                $currency = $this->customer->calculatePrimaryCurrency();
                // otherwise default to account currency
            } else {
                $currency = $this->company->currency;
            }
        }

        // validate currency
        if (!Currencies::exists($currency)) {
            throw new \Exception("Invalid currency: $currency");
        }

        return $currency;
    }

    private function getLastEventId(): int
    {
        // NOTE for now this looks for the account's global last event
        // and does not take into account only the customer's events,
        // if chosen
        return $this->database->createQueryBuilder()
            ->select('id')
            ->from('Events')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->orderBy('id', 'DESC')
            ->setMaxResults(1)
            ->fetchOne();
    }

    private function build(string $currency): array
    {
        // This is used to exclude future invoices from the current
        // outstanding amounts.
        $date = CarbonImmutable::now()->endOfDay();

        $aging = $this->aging($currency, $date);

        // the amount outstanding can be computed from the
        // aging buckets since they include all outstanding
        // invoices. this is a performance optimization.
        $numOutstanding = 0;
        $outstanding = new Money($currency, 0);
        $agingResult = [];
        foreach ($aging as $row) {
            $outstanding = $outstanding->add($row['amount']);
            $numOutstanding += $row['count'];

            $row['amount'] = $row['amount']->toDecimal();
            $agingResult[] = $row;
        }

        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);
        $agingDate = $agingBreakdown->dateColumn;

        $return = [
            'currency' => $currency,
            'total_invoices_outstanding' => $outstanding->toDecimal(),
            'num_invoices_outstanding' => $numOutstanding,
            'aging' => $agingResult,
            'aging_date' => $agingDate,
        ];

        if ($this->customer) {
            $return['outstanding'] = $this->getOutstanding(self::NUM_OUTSTANDING, $currency, $date);
        }

        if ($this->company->features->has('subscriptions')) {
            $mrr = $this->mrr($currency);
            $return['mrr'] = $mrr->toDecimal();
        }

        return $return;
    }

    //
    // Metrics
    //

    /**
     * Gets the N oldest outstanding invoices.
     */
    public function getOutstanding(int $n, string $currency, CarbonImmutable $date): array
    {
        $query = $this->database->createQueryBuilder()
            ->select('i.id,i.name,i.number,i.currency,i.balance,i.total,i.date,i.due_date,i.status,c.name as customerName')
            ->from('Invoices', 'i')
            ->join('i', 'Customers', 'c', 'i.customer=c.id')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('i.paid = 0')
            ->andWhere('i.draft = 0')
            ->andWhere('i.closed = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date <= '.$date->getTimestamp())
            ->orderBy('i.date', 'ASC')
            ->setMaxResults($n);

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('c.id')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('c.owner_id', $this->member->user_id);
            }
        }

        if ($this->customer) {
            $query->andWhere('customer = :customer')
                ->setParameter('customer', $this->customer->id());
        }

        $invoices = $query->fetchAllAssociative();
        foreach ($invoices as &$invoice) {
            $invoice['balance'] = (float) $invoice['balance'];
            $invoice['total'] = (float) $invoice['total'];
        }

        return $invoices;
    }

    /**
     * Gets the Monthly Recurring Revenue.
     */
    public function mrr(string $currency): Money
    {
        $query = $this->database->createQueryBuilder()
            ->select('sum(mrr)')
            ->from('Subscriptions', 's')
            ->join('s', 'Plans', 'p', 's.plan_id=p.internal_id')
            ->andWhere('s.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('p.currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('finished = 0')
            ->andWhere('canceled = 0')
            ->andWhere('status <> "'.SubscriptionStatus::TRIALING.'"')
            ->andWhere('renews_next IS NOT NULL');

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('s.customer')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        if ($this->customer) {
            $query->andWhere('customer = :customer')
                ->setParameter('customer', $this->customer->id());
        }

        $amount = $query->fetchOne();

        return Money::fromDecimal($currency, $amount ?? 0);
    }

    /**
     * Gets the aging breakdown for outstanding invoices.
     *
     * @return array [[age_lower => 0, amount => Money(), count => N], ...]
     */
    public function aging(string $currency, CarbonImmutable $date): array
    {
        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_receivable_settings);
        $aging = new AgingReport($agingBreakdown, $this->company, $this->database);
        $aging->setDate($date);

        if ($this->member) {
            $aging->setMember($this->member);
        }

        if ($this->customer) {
            $agingReport = $aging->buildForCustomer((int) $this->customer->id(), $currency)[$this->customer->id()];
        } else {
            $agingReport = $aging->buildForCompany($currency);
        }

        // parse the aging out of the report
        $result = [];
        $agingBuckets = $agingBreakdown->getBuckets();
        foreach ($agingBuckets as $i => $bucket) {
            $result[] = [
                'age_lower' => $bucket['lower'],
                'amount' => $agingReport[$i]['amount'],
                'count' => $agingReport[$i]['count'],
            ];
        }

        return $result;
    }

    /**
     * Gets the cache key name.
     */
    private function getCacheKey(string $metric): string
    {
        $k = 'dashboard_metric.2.'.$metric.'.'.$this->company->id();

        if ($this->member && Member::OWNER_RESTRICTION === $this->member->restriction_mode) {
            $k .= '.'.$this->member->id();
        }

        if (isset($this->restrictionQueryBuilder)) {
            $k .= '.'.md5((string) json_encode($this->restrictionQueryBuilder->getRestrictions()));
        }

        if ($this->customer) {
            $k .= '.'.$this->customer->id();
        }

        return $k;
    }
}
