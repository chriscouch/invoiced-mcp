<?php

namespace App\Integrations\QuickBooksOnline\Readers;

use App\Integrations\AccountingSync\Traits\InvoiceReaderTrait;

class QuickBooksInvoiceReader extends AbstractQuickBooksReader
{
    use InvoiceReaderTrait;
}
