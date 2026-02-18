<?php

namespace App\Tests\AccountsReceivable\Libs;

use App\AccountsReceivable\ValueObjects\CreditNoteStatus;
use App\AccountsReceivable\Models\CreditNote;
use App\Tests\AppTestCase;

class CreditNoteStatusTest extends AppTestCase
{
    public function testDraft(): void
    {
        $creditNote = new CreditNote();
        $creditNote->draft = true;
        $status = new CreditNoteStatus($creditNote);

        $this->assertEquals(CreditNoteStatus::DRAFT, $status->get());
    }

    public function testOpen(): void
    {
        $creditNote = new CreditNote();
        $status = new CreditNoteStatus($creditNote);

        $this->assertEquals(CreditNoteStatus::OPEN, $status->get());
    }

    public function testClosed(): void
    {
        $creditNote = new CreditNote();
        $creditNote->viewed = true;
        $creditNote->sent = true;
        $creditNote->closed = true;
        $status = new CreditNoteStatus($creditNote);

        $this->assertEquals(CreditNoteStatus::CLOSED, $status->get());
    }

    public function testPaid(): void
    {
        $creditNote = new CreditNote();
        $creditNote->viewed = true;
        $creditNote->sent = true;
        $creditNote->paid = true;
        $status = new CreditNoteStatus($creditNote);

        $this->assertEquals(CreditNoteStatus::PAID, $status->get());
    }

    public function testVoided(): void
    {
        $creditNote = new CreditNote();
        $creditNote->voided = true;
        $status = new CreditNoteStatus($creditNote);

        $this->assertEquals(CreditNoteStatus::VOIDED, $status->get());
    }
}
