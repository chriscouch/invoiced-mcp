<?php

namespace App\Tests\Statements\StatementLines\OpenItem;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\StatementLines\OpenItem\OpenCreditNoteStatementLine;
use App\Tests\AppTestCase;

class CreditNoteStatementLineTest extends AppTestCase
{
    private function getCreditNote(): CreditNote
    {
        $creditNote = new CreditNote([
            'currency' => 'usd',
            'number' => 'CN-00001',
            'total' => 100.0,
            'balance' => 20.0,
            'date' => mktime(0, 0, 0, 5, 4, 2021),
        ]);
        $customer = new Customer(['id' => -1]);
        $creditNote->setCustomer($customer);

        return $creditNote;
    }

    public function testGetLineTotal(): void
    {
        $creditNote = $this->getCreditNote();
        $line = new OpenCreditNoteStatementLine($creditNote);

        $this->assertEquals(new Money('usd', -10000), $line->getLineTotal());
    }

    public function testGetLineBalance(): void
    {
        $creditNote = $this->getCreditNote();
        $line = new OpenCreditNoteStatementLine($creditNote);

        $this->assertEquals(new Money('usd', -2000), $line->getLineBalance());
    }

    public function testGetDate(): void
    {
        $creditNote = $this->getCreditNote();
        $line = new OpenCreditNoteStatementLine($creditNote);

        $this->assertEquals((int) mktime(0, 0, 0, 5, 4, 2021), $line->getDate());
    }

    public function testBuild(): void
    {
        $creditNote = $this->getCreditNote();
        $line = new OpenCreditNoteStatementLine($creditNote);

        $this->assertEquals([
            'creditNote' => $creditNote,
            'customer' => $creditNote->customer(),
            'number' => 'CN-00001',
            'url' => null,
            'date' => 1620086400,
            'dueDate' => null,
            'total' => -100.0,
            'balance' => -20.0,
        ], $line->build());
    }
}
