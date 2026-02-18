<?php

namespace App\Tests\Reports\Dashboard;

use App\Reports\DashboardMetrics\ActionItemsMetric;
use App\Reports\ValueObjects\DashboardContext;
use App\Sending\Email\Models\EmailThread;
use App\Tests\AppTestCase;

class ActionItemsTest extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
        self::$company->features->enable('inboxes');
        self::hasInbox();
    }

    private function getActionItems(): ActionItemsMetric
    {
        return self::getService('test.dashboard_metric_action_items');
    }

    public function testBuild(): void
    {
        $this->assertEquals([
            'accounting_system' => null,
            'count' => 0,
            'num_autopay_invoices_missing_payment_info' => 0,
            'num_broken_promises' => 0,
            'num_my_todo' => 0,
            'num_needs_attention' => 0,
            'num_open_disputes' => 0,
            'num_open_email_threads' => 0,
            'num_reconciliation_errors' => 0,
            'num_remittance_advice_exceptions' => 0,
            'num_unapplied_payments' => 0,
            'num_unapproved_payment_plans' => 0,
        ], $this->getActionItems()->build(new DashboardContext(self::$company), []));
    }

    public function testTotalBrokenPromises(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalBrokenPromises());
    }

    public function testTotalNeedsAttention(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalNeedsAttention());
    }

    public function testTotalUnapprovedPaymentPlans(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalUnapprovedPaymentPlans());
    }

    public function testTotalAutoPayInvoicesMissingPaymentInfo(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalAutoPayInvoicesMissingPaymentInfo());
    }

    public function testTotalUnappliedPayments(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalUnappliedPayments());
    }

    public function testTotalMyDueTasks(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalMyDueTasks());
    }

    public function testGetTotalOpenEmailThreads(): void
    {
        $actionItems = $this->getActionItems();

        $this->assertEquals(0, $actionItems->getTotalOpenEmailThreads());

        $thread = new EmailThread();
        $thread->tenant_id = self::$company->id;
        $thread->status = 'closed';
        $thread->inbox = self::$inbox;
        $thread->name = 'test closed';
        $thread->saveOrFail();
        $thread = new EmailThread();
        $thread->tenant_id = self::$company->id;
        $thread->status = 'open';
        $thread->inbox = self::$inbox;
        $thread->name = 'test open';
        $thread->saveOrFail();
        $this->assertEquals(1, $actionItems->getTotalOpenEmailThreads());
    }

    public function testTotalOpenDisputes(): void
    {
        $this->assertEquals(0, $this->getActionItems()->getTotalOpenDisputes());
    }
}
