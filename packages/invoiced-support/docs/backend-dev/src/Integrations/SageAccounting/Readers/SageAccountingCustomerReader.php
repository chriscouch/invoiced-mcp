<?php

namespace App\Integrations\SageAccounting\Readers;

use App\Integrations\AccountingSync\Traits\CustomerReaderTrait;

class SageAccountingCustomerReader extends AbstractSageAccountingReader
{
    use CustomerReaderTrait;
}
