<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\ValueObjects\EstimateStatus;
use App\Tests\AppTestCase;

class EstimateStatusTest extends AppTestCase
{
    public function testNotSent(): void
    {
        $estimate = new Estimate();
        $status = new EstimateStatus($estimate);

        $this->assertEquals(EstimateStatus::NOT_SENT, $status->get());
    }

    public function testSent(): void
    {
        $estimate = new Estimate();
        $estimate->sent = true;
        $status = new EstimateStatus($estimate);

        $this->assertEquals(EstimateStatus::SENT, $status->get());
    }

    public function testViewed(): void
    {
        $estimate = new Estimate();
        $estimate->viewed = true;
        $estimate->sent = true;
        $status = new EstimateStatus($estimate);

        $this->assertEquals(EstimateStatus::VIEWED, $status->get());
    }

    public function testApproved(): void
    {
        $estimate = new Estimate();
        $estimate->approved = 'HEY';
        $estimate->viewed = true;
        $estimate->sent = true;
        $status = new EstimateStatus($estimate);

        $estimate['approved'] = 'HEY';
        $this->assertEquals(EstimateStatus::APPROVED, $status->get());
    }

    public function testDeclined(): void
    {
        $estimate = new Estimate();
        $estimate->approved = null;
        $estimate->closed = true;
        $estimate->viewed = true;
        $estimate->sent = true;
        $status = new EstimateStatus($estimate);

        $estimate['approved'] = null;
        $estimate['closed'] = true;
        $this->assertEquals(EstimateStatus::DECLINED, $status->get());
    }

    public function testInvoiced(): void
    {
        $estimate = new Estimate();
        $estimate->approved = 'HEY';
        $estimate->closed = true;
        $estimate->invoice_id = 100;
        $estimate->viewed = true;
        $estimate->sent = true;
        $status = new EstimateStatus($estimate);

        $this->assertEquals(EstimateStatus::INVOICED, $status->get());
    }

    public function testExpired(): void
    {
        $estimate = new Estimate();
        $estimate->closed = false;
        $estimate->expiration_date = time() - 100;
        $status = new EstimateStatus($estimate);

        $this->assertEquals(EstimateStatus::EXPIRED, $status->get());
    }

    public function testVoided(): void
    {
        $estimate = new Estimate();
        $estimate->voided = true;
        $status = new EstimateStatus($estimate);

        $this->assertEquals(EstimateStatus::VOIDED, $status->get());
    }
}
