<?php

namespace App\Integrations\SageAccounting\Readers;

use App\Integrations\AccountingSync\Traits\InvoiceReaderTrait;

class SageAccountingCreditNoteReader extends AbstractSageAccountingReader
{
    use InvoiceReaderTrait;
}
