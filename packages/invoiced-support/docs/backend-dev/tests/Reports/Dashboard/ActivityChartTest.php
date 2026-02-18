<?php

namespace App\Tests\Reports\Dashboard;

use App\CashApplication\Models\Payment;
use App\Companies\Models\Company;
use App\AccountsReceivable\Models\Invoice;
use App\Reports\Dashboard\ActivityChart;
use App\Tests\AppTestCase;

class ActivityChartTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();

        // TODO need more
        self::hasCustomer();
        self::hasInvoice();
        self::hasCreditNote();
        $invoice2 = new Invoice();
        $invoice2->setCustomer(self::$customer);
        $invoice2->items = [['unit_cost' => 100]];
        $invoice2->saveOrFail();

        // advance payment
        $payment = new Payment();
        $payment->setCustomer(self::$customer);
        $payment->amount = 50;
        $payment->applied_to = [['type' => 'credit', 'amount' => 50]];
        $payment->saveOrFail();
    }

    public function testGetSnappedTimestamp(): void
    {
        $chart = $this->getChart();

        /* Day */
        // beginning of period should not be snapped
        $ts = (int) mktime(0, 0, 0, 1, 1, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'day'), 'Start of period day timestamp snapping failed');

        // mid-period should be snapped
        $ts = (int) mktime(5, 10, 15, 1, 1, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'day'));

        // mid-period should be snapped (Sunday)
        $ts = (int) mktime(5, 10, 15, 1, 1, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'day'));

        /* Week */
        // NOTE assume the start of the week is monday
        // beginning of period should not be snapped
        $ts = (int) mktime(0, 0, 0, 1, 4, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 4, 2016), $chart->getSnappedTimestamp($ts, 'week'), 'Start of period week timestamp snapping failed');

        // mid-period should be snapped to previous Monday
        $ts = (int) mktime(0, 0, 0, 1, 10, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 4, 2016), $chart->getSnappedTimestamp($ts, 'week'), 'Mid-period week timestamp snapping failed');

        /* Month */
        // beginning of period should not be snapped
        $ts = (int) mktime(0, 0, 0, 1, 1, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'month'), 'Start of period month timestamp snapping failed');

        // mid-period should be snapped
        $ts = (int) mktime(0, 0, 0, 1, 15, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'month'), 'Mid-period month timestamp snapping failed');

        /* Year */
        // beginning of period should not be snapped
        $ts = (int) mktime(0, 0, 0, 1, 1, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'year'), 'Start of period year timestamp snapping failed');

        // mid-period should be snapped
        $ts = (int) mktime(0, 0, 0, 6, 15, 2016);
        $this->assertTimestampsEqual((int) mktime(0, 0, 0, 1, 1, 2016), $chart->getSnappedTimestamp($ts, 'year'), 'Mid-period year timestamp snapping failed');
    }

    public function testGetBuckets(): void
    {
        $chart = $this->getChart();

        $start = (int) mktime(0, 0, 0, 1, 1, 2014);
        $end = (int) mktime(0, 0, 0, 1, 1, 2015);

        $buckets = $chart->getBuckets($start, $end, 'month');

        $expected = [
            (int) mktime(0, 0, 0, 1, 1, 2014),
            (int) mktime(0, 0, 0, 2, 1, 2014),
            (int) mktime(0, 0, 0, 3, 1, 2014),
            (int) mktime(0, 0, 0, 4, 1, 2014),
            (int) mktime(0, 0, 0, 5, 1, 2014),
            (int) mktime(0, 0, 0, 6, 1, 2014),
            (int) mktime(0, 0, 0, 7, 1, 2014),
            (int) mktime(0, 0, 0, 8, 1, 2014),
            (int) mktime(0, 0, 0, 9, 1, 2014),
            (int) mktime(0, 0, 0, 10, 1, 2014),
            (int) mktime(0, 0, 0, 11, 1, 2014),
            (int) mktime(0, 0, 0, 12, 1, 2014),
        ];

        $this->assertEquals($expected, $buckets);
    }

    public function testGetBucketsCentralTime(): void
    {
        $company = new Company();
        $company->time_zone = 'America/Chicago';
        $chart = $this->getChart($company);

        $start = 1514786400; // Jan-1-2018 CST
        $end = 1540702799; // Oct-27-2018 23:59:59 CST

        $buckets = $chart->getBuckets($start, $end, 'month');

        $expected = [
            1514786400, // Jan-1-2018
            1517464800, // Feb-1-2018
            1519884000, // Mar-1-2018
            1522558800, // Apr-1-2018
            1525150800, // May-1-2018
            1527829200, // Jun-1-2018
            1530421200, // Jul-1-2018
            1533099600, // Aug-1-2018
            1535778000, // Sep-1-2018
            1538370000, // Oct-1-2018
        ];

        $this->assertEquals($expected, $buckets);
    }

    public function testGetBucketsNewZealandTime(): void
    {
        $company = new Company();
        $company->time_zone = 'Pacific/Auckland';
        $chart = $this->getChart($company);

        $start = 1514786400; // Jan-1-2018 CST
        $end = 1540702799; // Oct-27-2018 23:59:59 CST

        $buckets = $chart->getBuckets($start, $end, 'month');

        $expected = [
            1514786400, // Jan-1-2018 CST
            1517396400, // Feb-1-2018 NZT
            1519815600, // Mar-1-2018 NZT
            1522494000, // Apr-1-2018 NZT
            1525089600, // May-1-2018 NZT
            1527768000, // Jun-1-2018 NZT
            1530360000, // Jul-1-2018 NZT
            1533038400, // Aug-1-2018 NZT
            1535716800, // Sep-1-2018 NZT
            1538305200, // Oct-1-2018 NZT
        ];

        $this->assertEquals($expected, $buckets);
    }

    public function testGetBucketsSnapping(): void
    {
        $chart = $this->getChart();

        // Whenever the start or end date do not fall on the
        // calendar start of a time period (month in this case)
        // then the buckets should be snapped to the beginning
        // of the period. In this test case we are starting
        // on Jan 15 with a month duration. The first bucket
        // should always match the start date, but the next
        // buckets should be snapped to calendar months, i.e.
        // Feb 1, Mar 1, and so on...
        // NOTE: this is time zone specific
        $start = (int) mktime(0, 0, 0, 1, 15, 2014);
        $end = (int) mktime(0, 0, 0, 1, 15, 2015);

        $buckets = $chart->getBuckets($start, $end, 'month');

        $expected = [
            (int) mktime(0, 0, 0, 1, 15, 2014),
            (int) mktime(0, 0, 0, 2, 1, 2014),
            (int) mktime(0, 0, 0, 3, 1, 2014),
            (int) mktime(0, 0, 0, 4, 1, 2014),
            (int) mktime(0, 0, 0, 5, 1, 2014),
            (int) mktime(0, 0, 0, 6, 1, 2014),
            (int) mktime(0, 0, 0, 7, 1, 2014),
            (int) mktime(0, 0, 0, 8, 1, 2014),
            (int) mktime(0, 0, 0, 9, 1, 2014),
            (int) mktime(0, 0, 0, 10, 1, 2014),
            (int) mktime(0, 0, 0, 11, 1, 2014),
            (int) mktime(0, 0, 0, 12, 1, 2014),
            (int) mktime(0, 0, 0, 1, 1, 2015),
        ];

        $this->assertEquals($expected, $buckets);
    }

    public function testGenerateLabelDay(): void
    {
        $chart = $this->getChart();
        $length = 'day';

        $this->assertEquals('Today', $chart->generateLabel(time(), $length));
        $this->assertEquals('Today', $chart->generateLabel(time() + 86399, $length));
        $this->assertEquals('Today', $chart->generateLabel(time() - 86399, $length));

        $this->assertEquals('Jun 20', $chart->generateLabel((int) mktime(0, 0, 0, 6, 20, 2015), $length));
    }

    public function testGenerateLabelWeek(): void
    {
        $chart = $this->getChart();
        $length = 'week';

        $this->assertEquals('Jun 20', $chart->generateLabel((int) mktime(0, 0, 0, 6, 20, 2015), $length));
    }

    public function testGenerateLabelMonth(): void
    {
        $chart = $this->getChart();
        $length = 'month';

        $this->assertEquals('Jun', $chart->generateLabel((int) mktime(0, 0, 0, 6, 20, 2015), $length));
        $this->assertEquals('Dec', $chart->generateLabel((int) mktime(0, 0, 0, 12, 20, 2015), $length));
    }

    public function testGenerate(): void
    {
        $chart = $this->getChart();

        $start = time() - 30;
        $end = time() + 30;
        $data = $chart->generate(null, $start, $end);

        $expected = [
            'currency' => 'usd',
            'start' => $start,
            'end' => $end,
            'invoices' => [
                $start => 100.0,
            ],
            'payments' => [
                $start => 50.0,
            ],
            'labels' => [
                $start => 'Today',
            ],
            'unit' => 'day',
        ];

        $this->assertEquals($expected, $data);
    }

    public function testGenerateWithCurrency(): void
    {
        $chart = $this->getChart();

        $start = time() - 30;
        $end = time() + 30;
        $data = $chart->generate('eur', $start, $end);

        $expected = [
            'currency' => 'eur',
            'start' => $start,
            'end' => $end,
            'invoices' => [
                $start => 0,
            ],
            'payments' => [
                $start => 0,
            ],
            'labels' => [
                $start => 'Today',
            ],
            'unit' => 'day',
        ];

        $this->assertEquals($expected, $data);
    }

    public function testGenerateCustomer(): void
    {
        $chart = $this->getChart();

        $data = $chart->generate(null, 0, 0, self::$customer);

        $start = (int) strtotime('first day of -11 months');
        $start = (int) mktime(0, 0, 0, (int) date('n', $start), 1, (int) date('Y', $start));

        $end = (int) mktime(23, 59, 59, (int) date('n'), (int) date('t'), (int) date('Y'));

        // should create a bucket for each month
        $labels = [];
        $template = [];
        $t = $start;
        for ($i = 0; $i < 11; ++$i) {
            $template[$t] = 0;
            $labels[$t] = date('M', $t);
            $t = strtotime('+1 month', $t);
        }

        // last bucket should contain our invoice
        $invoices = array_replace($template, [$t => 100.0]);

        // last bucket should contain our payment
        $payments = array_replace($template, [$t => 50.0]);

        // last bucket label
        $labels[$t] = date('M', $t);

        $expected = [
            'currency' => 'usd',
            'start' => $start,
            'end' => $end,
            'invoices' => $invoices,
            'payments' => $payments,
            'labels' => $labels,
            'unit' => 'month',
        ];

        $this->assertEquals($expected, $data);
    }

    private function getChart(?Company $company = null): ActivityChart
    {
        $chart = self::getService('test.activity_chart');
        $chart->setCompany($company ?? self::$company);

        return $chart;
    }
}
