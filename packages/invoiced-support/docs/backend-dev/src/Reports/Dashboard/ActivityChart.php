<?php

namespace App\Reports\Dashboard;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Member;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\ReportHelper;
use App\Reports\Traits\MemberAwareDashboardTrait;
use Doctrine\DBAL\Connection;

class ActivityChart
{
    use MemberAwareDashboardTrait;

    const LENGTH_YEAR = 'year';
    const LENGTH_MONTH = 'month';
    const LENGTH_WEEK = 'week';
    const LENGTH_DAY = 'day';

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Generates an activity chart.
     *
     * @param int      $start    period begin
     * @param int      $end      period end
     * @param Customer $customer optional customer
     *
     * @throws \InvalidArgumentException if the date range is invalid
     */
    public function generate(?string $currency = null, int $start = 0, int $end = 0, Customer $customer = null): array
    {
        if (!$currency) {
            // use customer's primary currency when appropriate
            if ($customer) {
                $currency = $customer->calculatePrimaryCurrency();
                // otherwise default to account currency
            } else {
                $currency = $this->company->currency;
            }
        }

        if (!$start) {
            // When using PHP relative time strings on the last
            // day of the month can sometimes cause miscalculations.
            // The fix is to specify a day of the month in order
            // to ensure the intervals are calculated as one would expect.
            // See http://derickrethans.nl/obtaining-the-next-month-in-php.html for a more detailed explanation.
            $start = $this->roundTsDown(strtotime('first day of -11 months'));
        }

        if (!$end) {
            $end = $this->roundTsUp(time());
        }

        if ($start >= $end) {
            throw new \InvalidArgumentException('End date cannot be greater than start date');
        }

        // determine how long each 'bucket' should be
        $bucketLength = $this->getBucketLength($start, $end);

        // Create a 'bucket' for every period in the time range.
        // Each bucket represents the start date of the period.
        $buckets = $this->getBuckets($start, $end, $bucketLength);

        // build the bucket labels
        $labels = $this->getBucketLabels($buckets, $bucketLength);

        // build invoice time series
        $invoices = $this->getInvoiceTimeSeries($currency, $buckets, $bucketLength, $start, $end, $customer);
        foreach ($invoices as &$invoice) {
            $invoice = $invoice->toDecimal();
        }

        // build payment time series
        $payments = $this->getPaymentTimeSeries($currency, $buckets, $bucketLength, $start, $end, $customer);
        foreach ($payments as &$payment) {
            $payment = $payment->toDecimal();
        }

        return [
            'currency' => $currency,
            'start' => $start,
            'end' => $end,
            'unit' => $bucketLength,
            'labels' => $labels,
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    //
    // Helpers
    //

    /**
     * Heuristic to determine the time duration of a single
     * bucket for time series data.
     */
    public function getBucketLength(int $start, int $end): string
    {
        $span = ceil(($end - $start) / 86400); // days
        if ($span > 1095) { // 3 years
            return self::LENGTH_YEAR;
        } elseif ($span > 90) { // 3 months
            return self::LENGTH_MONTH;
        } elseif ($span > 21) { // 3 weeks
            return self::LENGTH_WEEK;
        }

        return self::LENGTH_DAY;
    }

    /**
     * Snaps a timestamp to the start of the calendar period
     * it is in, i.e. day, week, month.
     *
     * NOTE: snapping is relative to the company's time zone.
     *
     * @param int    $t      timestamp
     * @param string $period i.e. 'day', 'week', 'month'
     */
    public function getSnappedTimestamp(int $t, string $period): int
    {
        if (self::LENGTH_DAY === $period) {
            return (int) mktime(0, 0, 0, (int) date('n', $t), (int) date('j', $t), (int) date('Y', $t));
        } elseif (self::LENGTH_WEEK === $period) {
            // Assume Monday is start of week
            // Returns 0 - 6 (1 = Monday)
            $dayOfWeek = date('w', $t);
            // Sunday: -6
            // Monday: NOOP
            // Tuesday: -1
            // Wednesday: -2
            // Thursday: -3
            // Friday: -4
            // Saturday: -5
            $subtract = 0;
            if (0 == $dayOfWeek) { // Sunday
                $subtract = 6;
            } elseif ($dayOfWeek > 0) {
                $subtract = $dayOfWeek - 1;
            }
            $startOfWeek = ($subtract > 0) ? (int) strtotime("-$subtract days", $t) : $t;

            return (int) mktime(0, 0, 0, (int) date('n', $startOfWeek), (int) date('j', $startOfWeek), (int) date('Y', $startOfWeek));
        } elseif (self::LENGTH_MONTH === $period) {
            return (int) mktime(0, 0, 0, (int) date('n', $t), 1, (int) date('Y', $t));
        } elseif (self::LENGTH_YEAR === $period) {
            return (int) mktime(0, 0, 0, 1, 1, (int) date('Y', $t));
        }

        return $t;
    }

    /**
     * Builds an ordered list of time "buckets" for time-series
     * data. Each bucket represents the start date of a period.
     *
     * If the start timestamp does not fall squarely
     * on the start of a calendar period for the given
     * length then any future periods will be snapped to line
     * up with the start of that period.
     * This is called *snapping*.
     * For example, if we are generating month buckets with a
     * start date that is not the start of the calendar month
     * then the start of any sequential buckets should fall on
     * the start of calendar months to produce more useful data.
     * The first bucket should ALWAYS be the start date.
     * Similarly, the last bucket should NEVER be greater
     * than the end date.
     * NOTE: snapping is relative to the company's time zone.
     *
     * @param string $length i.e. 'day', 'week', 'month'
     */
    public function getBuckets(int $start, int $end, string $length): array
    {
        // the first bucket is ALWAYS the start date
        $buckets = [$start];

        // snap the next bucket to the next calendar period
        // immediately after the start date
        $bucket = $this->getSnappedTimestamp($start, $length);
        $bucket = (int) strtotime("+1 $length", $bucket);

        // the last bucket should NEVER exceed the end date
        while ($bucket < $end) {
            $buckets[] = $bucket;
            $bucket = (int) strtotime("+1 $length", $bucket);
        }

        return $buckets;
    }

    /**
     * Generates the chart labels for each bucket. The benefit of
     * generating the labels server-side is that we can ensure the
     * company's time zone is used.
     *
     * @param string $length i.e. 'day', 'week', 'month'
     */
    public function getBucketLabels(array $buckets, string $length): array
    {
        $labels = [];
        foreach ($buckets as $t) {
            $labels[$t] = $this->generateLabel($t, $length);
        }

        return $labels;
    }

    /**
     * Generates the chart label for a given timestamp. The labels
     * depend on the period length that is used, i.e. day or month.
     *
     * @param int    $t      timestamp
     * @param string $length i.e. 'day', 'week', 'month'
     */
    public function generateLabel(int $t, string $length): string
    {
        // year format is "2018"
        if (self::LENGTH_YEAR == $length) {
            return date('Y', $t);
        }

        // month format is "Dec"
        if (self::LENGTH_MONTH == $length) {
            return date('M', $t);
        }

        // return "Today" when talking about current day
        if (self::LENGTH_DAY == $length) {
            $dayDiff = floor(abs($t - time()) / 86400);
            if (0 == $dayDiff) {
                return 'Today';
            }
        }

        // default format is "Dec 20"
        return date('M j', $t);
    }

    /**
     * Rounds the timestamp down to the beginning of month
     * relative to the current time zone.
     *
     * @param int $t timestamp
     */
    private function roundTsDown(int $t): int
    {
        return (int) mktime(0, 0, 0, (int) date('n', $t), 1, (int) date('Y', $t));
    }

    /**
     * Rounds the timestamp up to the end of month
     * relative to the current time zone.
     *
     * @param int $t timestamp
     */
    private function roundTsUp(int $t): int
    {
        return (int) mktime(23, 59, 59, (int) date('n', $t), (int) date('t', $t), (int) date('Y', $t));
    }

    /**
     * Builds an invoice time series. This deducts
     * credit notes from the amount invoiced within
     * the same time period.
     *
     * @param int $start starting timestamp
     * @param int $end   ending timestamp
     */
    private function getInvoiceTimeSeries(string $currency, array $buckets, string $bucketLength, int $start, int $end, ?Customer $customer): array
    {
        [$dbDateFormat, $phpDateFormat] = $this->getDateFormat($bucketLength);

        [$subQuery1, $params1] = $this->buildInvoiceSubQuery(true, $currency, $start, $end, $customer);
        [$subQuery2, $params2] = $this->buildInvoiceSubQuery(false, $currency, $start, $end, $customer);

        $sql = "SELECT FROM_UNIXTIME(`date`, '$dbDateFormat') AS t,SUM(total) AS amount";
        $sql .= ' FROM ('.$subQuery1.' UNION ALL '.$subQuery2.') data';
        $sql .= ' GROUP BY t';
        $params = array_replace($params1, $params2);

        $rows = $this->database->fetchAllAssociative($sql, $params);

        $mapping = [];
        foreach ($rows as $row) {
            $mapping[$row['t']] = Money::fromDecimal($currency, $row['amount']);
        }

        // build the result
        $result = [];
        foreach ($buckets as $i => $bucket) {
            $key = date($phpDateFormat, $bucket);

            if (isset($mapping[$key])) {
                $amount = $mapping[$key];
            } else {
                $amount = new Money($currency, 0);
            }

            $result[$bucket] = $amount;
        }

        return $result;
    }

    private function getDateFormat(string $bucketLength): array
    {
        if (self::LENGTH_YEAR == $bucketLength) {
            return ['%Y', 'Y'];
        }

        if (self::LENGTH_MONTH == $bucketLength) {
            return ['%Y-%m', 'Y-m'];
        }

        if (self::LENGTH_WEEK == $bucketLength) {
            return ['%v', 'W'];
        }

        return ['%Y-%m-%d', 'Y-m-d'];
    }

    private function buildInvoiceSubQuery(bool $isInvoice, string $currency, int $start, int $end, ?Customer $customer): array
    {
        $table = $isInvoice ? 'Invoices' : 'CreditNotes';
        $params = [
            'tenantId' => $this->company->id(),
            'currency' => $currency,
            'start' => $start,
            'end' => $end,
        ];
        if ($isInvoice) {
            $sql = 'SELECT `date`, total FROM '.$table;
        } else {
            $sql = 'SELECT `date`, -total FROM '.$table;
        }
        $sql .= ' WHERE tenant_id=:tenantId and draft=0 AND voided=0 and currency=:currency and `date` BETWEEN :start AND :end';

        if ($customer) {
            $params['customer'] = $customer->id();
            $sql .= ' AND customer=:customer';
        }

        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql($table.'.customer')) {
                    $sql .= ' AND '.$restriction;
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $params['userId'] = $this->member->user_id;
                $sql .= ' AND '.$table.'.customer IN (SELECT id FROM Customers WHERE tenant_id=:tenantId AND owner_id=:userId)';
            }
        }

        return [$sql, $params];
    }

    /**
     * Builds a payment time series.
     *
     * Applied credits are not included in this.
     *
     * @param int $start starting timestamp
     * @param int $end   ending timestamp
     */
    private function getPaymentTimeSeries(string $currency, array $buckets, string $bucketLength, int $start, int $end, ?Customer $customer): array
    {
        [$dbDateFormat, $phpDateFormat] = $this->getDateFormat($bucketLength);

        // fetch the payment time series data
        [$subQuery1, $params1] = $this->buildPaymentsSubQuery($currency, $start, $end, $customer);
        [$subQuery2, $params2] = $this->buildLegacyTransactionsSubQuery($currency, $start, $end, $customer);

        $sql = "SELECT FROM_UNIXTIME(`date`, '$dbDateFormat') AS t,SUM(amount) as amount";
        $sql .= ' FROM ('.$subQuery1.' UNION ALL '.$subQuery2.') data';
        $sql .= ' GROUP BY t';
        $params = array_replace($params1, $params2);

        $payments = $this->database->fetchAllAssociative($sql, $params);

        $mapping = [];
        foreach ($payments as $row) {
            $mapping[$row['t']] = Money::fromDecimal($currency, $row['amount']);
        }

        // build the result
        $result = [];
        foreach ($buckets as $i => $bucket) {
            $key = date($phpDateFormat, $bucket);

            if (isset($mapping[$key])) {
                $result[$bucket] = $mapping[$key];
            } else {
                $result[$bucket] = new Money($currency, 0);
            }
        }

        return $result;
    }

    /**
     * Calculates payments created with a payment object.
     */
    private function buildPaymentsSubQuery(string $currency, int $start, int $end, ?Customer $customer): array
    {
        $params = [
            'tenantId' => $this->company->id(),
            'currency' => $currency,
            'start' => $start,
            'end' => $end,
        ];
        $sql = 'SELECT `date`,amount';
        $sql .= ' FROM Payments';
        $sql .= ' WHERE tenant_id=:tenantId and voided=0 and currency=:currency and `date` BETWEEN :start AND :end';

        if ($customer) {
            $params['customer'] = $customer->id();
            $sql .= ' AND customer=:customer';
        }

        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Payments.customer')) {
                    $sql .= ' AND '.$restriction;
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $params['userId'] = $this->member->user_id;
                $sql .= ' AND Payments.customer IN (SELECT id FROM Customers WHERE tenant_id=:tenantId AND owner_id=:userId)';
            }
        }

        return [$sql, $params];
    }

    /**
     * Calculates payments created without a payment object.
     */
    private function buildLegacyTransactionsSubQuery(string $currency, int $start, int $end, ?Customer $customer): array
    {
        $params = [
            'tenantId' => $this->company->id(),
            'currency' => $currency,
            'start' => $start,
            'end' => $end,
        ];
        $sql = "SELECT `date`,CASE WHEN `type`='refund' THEN -amount ELSE amount END as amount";
        $sql .= ' FROM Transactions';
        $sql .= " WHERE tenant_id=:tenantId and status='succeeded' and type <> 'adjustment' and (type <> 'charge' OR method <> 'balance') and currency=:currency and `date` BETWEEN :start AND :end AND payment_id IS NULL";

        if ($customer) {
            $params['customer'] = $customer->id();
            $sql .= ' AND customer=:customer';
        }

        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Transactions.customer')) {
                    $sql .= ' AND '.$restriction;
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $params['userId'] = $this->member->user_id;
                $sql .= ' AND Transactions.customer IN (SELECT id FROM Customers WHERE tenant_id=:tenantId AND owner_id=:userId)';
            }
        }

        return [$sql, $params];
    }
}
