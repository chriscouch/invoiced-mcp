<?php

namespace App\Integrations\Xero\Readers;

use App\Integrations\AccountingSync\Traits\InvoiceReaderTrait;

class XeroInvoiceReader extends AbstractXeroReader
{
    use InvoiceReaderTrait;
}
