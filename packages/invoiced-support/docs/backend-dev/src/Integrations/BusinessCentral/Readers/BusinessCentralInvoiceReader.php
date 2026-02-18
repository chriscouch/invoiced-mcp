<?php

namespace App\Integrations\BusinessCentral\Readers;

use App\Integrations\AccountingSync\Traits\InvoiceReaderTrait;

class BusinessCentralInvoiceReader extends AbstractBusinessCentralReader
{
    use InvoiceReaderTrait;
}
