<?php

namespace App\Tests\Statements\StatementLines\OpenItem;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\Statements\StatementLines\OpenItem\OpenCreditNoteStatementLine;
use App\Statements\StatementLines\OpenItem\OpenInvoiceStatementLine;
use App\Statements\StatementLines\OpenItemStatementLineFactory;
use App\Tests\AppTestCase;

class OpenItemStatementLineFactoryTest extends AppTestCase
{
    private function getFactory(): OpenItemStatementLineFactory
    {
        return new OpenItemStatementLineFactory();
    }

    public function testMakeInvoice(): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(OpenInvoiceStatementLine::class, $factory->make(new Invoice()));
    }

    public function testMakeCreditNote(): void
    {
        $factory = $this->getFactory();
        $this->assertInstanceOf(OpenCreditNoteStatementLine::class, $factory->make(new CreditNote()));
    }
}
