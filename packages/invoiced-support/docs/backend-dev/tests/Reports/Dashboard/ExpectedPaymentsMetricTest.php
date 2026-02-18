<?php

namespace App\Tests\Reports\Dashboard;

use App\Chasing\Models\PromiseToPay;
use App\Reports\DashboardMetrics\ExpectedPaymentsMetric;
use App\Reports\ValueObjects\DashboardContext;
use App\Tests\AppTestCase;

class ExpectedPaymentsMetricTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::hasCustomer();
        self::hasInvoice();
    }

    private function getMetric(): ExpectedPaymentsMetric
    {
        return self::getService('test.dashboard_metric_expected_payments');
    }

    public function testGetNoData(): void
    {
        $metrics = $this->getMetric();

        $total = $metrics->build(new DashboardContext(self::$company), ['currency' => 'usd']);
        $this->assertEquals('usd', $total['currency']);
        $this->assertEquals(0, $total['total']);
    }

    public function testGetPromiseToPay(): void
    {
        $metrics = $this->getMetric();

        $expectedPaymentDate = new PromiseToPay();
        $expectedPaymentDate->invoice = self::$invoice;
        $expectedPaymentDate->customer = self::$customer;
        $expectedPaymentDate->date = strtotime('+1 month');
        $expectedPaymentDate->currency = self::$invoice->currency;
        $expectedPaymentDate->amount = self::$invoice->balance;
        $expectedPaymentDate->saveOrFail();

        $total = $metrics->build(new DashboardContext(self::$company), ['currency' => 'usd']);
        $this->assertEquals('usd', $total['currency']);
        $this->assertEquals(100, $total['total']);

        $expectedPaymentDate->date = null;
        $expectedPaymentDate->saveOrFail();

        $total = $metrics->build(new DashboardContext(self::$company), ['currency' => 'usd']);
        $this->assertEquals('usd', $total['currency']);
        $this->assertEquals(0, $total['total']);
    }

    public function testGetAutoPay(): void
    {
        $metrics = $this->getMetric();

        PromiseToPay::where('invoice_id', self::$invoice->id())->delete();

        self::$invoice->autopay = true;
        self::$invoice->saveOrFail();

        $total = $metrics->build(new DashboardContext(self::$company), ['currency' => 'usd']);
        $this->assertEquals('usd', $total['currency']);
        $this->assertEquals(100, $total['total']);
    }
}
