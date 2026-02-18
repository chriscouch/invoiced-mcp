<?php

namespace App\Integrations\FreshBooks\Readers;

use App\Integrations\AccountingSync\Traits\InvoiceReaderTrait;

class FreshBooksInvoiceReader extends AbstractFreshBooksReader
{
    use InvoiceReaderTrait;
}
