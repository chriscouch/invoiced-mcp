<?php

namespace App\Integrations\SageAccounting\Readers;

use App\Integrations\AccountingSync\Traits\InvoiceReaderTrait;

class SageAccountingInvoiceReader extends AbstractSageAccountingReader
{
    use InvoiceReaderTrait;
}
