<?php

namespace App\Tests\Reports\Dashboard;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Member;
use App\Reports\Dashboard\Dashboard;
use App\Reports\DashboardMetrics\CollectionsEfficiencyMetric;
use App\Reports\DashboardMetrics\DaysSalesOutstandingMetric;
use App\Reports\DashboardMetrics\TimeToPayMetric;
use App\Reports\DashboardMetrics\TopDebtorsMetric;
use App\Reports\ValueObjects\DashboardContext;
use App\Tests\AppTestCase;

class DashboardTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
        self::$company->features->enable('inboxes');

        self::hasCustomer();
        self::hasInvoice();
        self::hasInbox();

        $voidedInvoice = new Invoice();
        $voidedInvoice->date = strtotime('-1 month');
        $voidedInvoice->setCustomer(self::$customer);
        $voidedInvoice->items = [['unit_cost' => 100]];
        $voidedInvoice->saveOrFail();
        $voidedInvoice->void();

        $creditNote = new CreditNote();
        $creditNote->setCustomer(self::$customer);
        $creditNote->items = [['unit_cost' => 1]];
        $creditNote->saveOrFail();
    }

    private function getDashboard(?Customer $customer = null): Dashboard
    {
        $dashboard = self::getService('test.dashboard');
        $dashboard->setCompany(self::$company);
        if ($customer) {
            $dashboard->setCustomer($customer);
        } else {
            $dashboard->setCustomer(null);
        }

        return $dashboard;
    }

    private function getTopDebtors(): TopDebtorsMetric
    {
        return self::getService('test.dashboard_metric_top_debtors');
    }

    private function getTimeToPay(): TimeToPayMetric
    {
        return self::getService('test.dashboard_metric_time_to_pay');
    }

    private function getDaysSalesOutstanding(): DaysSalesOutstandingMetric
    {
        return self::getService('test.dashboard_metric_dso');
    }

    private function getCollectionsEfficiency(): CollectionsEfficiencyMetric
    {
        return self::getService('test.dashboard_metric_collections_efficiency');
    }

    public function testGenerate(): void
    {
        $data = $this->getDashboard()->generate();

        $expected = [
            'currency' => 'usd',
            'total_invoices_outstanding' => 99.0,
            'num_invoices_outstanding' => 2,
            'aging' => [
                [
                    'amount' => 99.0,
                    'count' => 2,
                    'age_lower' => 0,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 8,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 15,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 31,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 61,
                ],
            ],
            'aging_date' => 'date',
            'mrr' => 0.0,
        ];

        $this->assertEquals($expected, $data);
    }

    public function testGenerateWithCurrency(): void
    {
        $data = $this->getDashboard()->generate('eur');

        $expected = [
            'currency' => 'eur',
            'total_invoices_outstanding' => 0.0,
            'num_invoices_outstanding' => 0,
            'aging' => [
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 0,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 8,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 15,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 31,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 61,
                ],
            ],
            'aging_date' => 'date',
            'mrr' => 0.0,
        ];

        $this->assertEquals($expected, $data);
    }

    public function testGenerateForCustomer(): void
    {
        $dashboard = $this->getDashboard(self::$customer);

        $data = $dashboard->generate();

        $expected = [
            'currency' => 'usd',
            'total_invoices_outstanding' => 99.0,
            'num_invoices_outstanding' => 2,
            'aging' => [
                [
                    'amount' => 99.0,
                    'count' => 2,
                    'age_lower' => 0,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 8,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 15,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 31,
                ],
                [
                    'amount' => 0.0,
                    'count' => 0,
                    'age_lower' => 61,
                ],
            ],
            'aging_date' => 'date',
            'outstanding' => [
                [
                    'id' => self::$invoice->id(),
                    'name' => self::$invoice->name,
                    'number' => self::$invoice->number,
                    'currency' => self::$invoice->currency,
                    'balance' => self::$invoice->balance,
                    'total' => self::$invoice->total,
                    'date' => self::$invoice->date,
                    'due_date' => self::$invoice->due_date,
                    'status' => self::$invoice->status,
                    'customerName' => self::$invoice->customer()->name,
                ],
            ],
            'mrr' => 0.0,
        ];

        $this->assertEquals($expected, $data);
    }

    public function testTopDebtors(): void
    {
        $metric = $this->getTopDebtors();

        $expected = [
            [
                'customer' => self::$customer->id(),
                'customerName' => self::$customer->name,
                'balance' => 99.0,
                'numInvoices' => 2,
                'age' => 0,
                'pastDue' => false,
            ],
        ];
        $context = new DashboardContext(self::$company);
        $this->assertEquals($expected, $metric->build($context, ['count' => 5, 'currency' => 'usd'])['top_debtors']);
        $this->assertEquals([], $metric->build($context, ['count' => 5, 'currency' => 'eur'])['top_debtors']);
    }

    public function testTopDebtorWithRestrictions(): void
    {
        $metric = $this->getTopDebtors();
        $member = new Member();
        $member->setUser(self::getService('test.user_context')->get());
        $member->restriction_mode = Member::CUSTOM_FIELD_RESTRICTION;
        $member->restrictions = ['territory' => ['Texas']];
        $context = new DashboardContext(self::$company, $member);

        $this->assertEquals([], $metric->build($context, ['count' => 5, 'currency' => 'usd'])['top_debtors']);

        self::$customer->metadata = (object) ['territory' => 'Texas'];
        self::$customer->saveOrFail();
        $expected = [
            [
                'customer' => self::$customer->id(),
                'customerName' => self::$customer->name,
                'balance' => 99.0,
                'numInvoices' => 2,
                'age' => 0,
                'pastDue' => false,
            ],
        ];
        $this->assertEquals($expected, $metric->build($context, ['count' => 5, 'currency' => 'usd'])['top_debtors']);
    }

    public function testAverageTimeToPay(): void
    {
        $metric = $this->getTimeToPay();
        $context = new DashboardContext(self::$company);
        $this->assertEquals([
            'average_time_to_pay' => -1,
        ], $metric->build($context, ['currency' => 'usd']));

        $context = new DashboardContext(self::$company, null, self::$customer);
        $this->assertEquals([
            'average_time_to_pay' => -1,
        ], $metric->build($context, ['currency' => 'usd']));
    }

    public function testCollectionsEfficiency(): void
    {
        $metric = $this->getCollectionsEfficiency();
        $context = new DashboardContext(self::$company);

        $this->assertEquals([
            'collections_efficiency' => 0.0,
        ], $metric->build($context, []));

        $this->assertEquals([
            'collections_efficiency' => -1,
        ], $metric->build($context, ['currency' => 'eur']));

        $context = new DashboardContext(self::$company, null, self::$customer);
        $this->assertEquals([
            'collections_efficiency' => 0.0,
        ], $metric->build($context, []));
    }

    public function testDaysSalesOutstanding(): void
    {
        $metric = $this->getDaysSalesOutstanding();
        $context = new DashboardContext(self::$company);

        $this->assertEquals([
            'dso' => 1.0,
        ], $metric->build($context, []));

        $this->assertEquals([
            'dso' => -1,
        ], $metric->build($context, ['currency' => 'eur']));

        $context = new DashboardContext(self::$company, null, self::$customer);
        $this->assertEquals([
            'dso' => 1.0,
        ], $metric->build($context, []));
    }
}
