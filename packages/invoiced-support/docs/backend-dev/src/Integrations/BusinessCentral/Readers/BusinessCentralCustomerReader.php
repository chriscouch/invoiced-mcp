<?php

namespace App\Integrations\BusinessCentral\Readers;

use App\Integrations\AccountingSync\Traits\CustomerReaderTrait;

class BusinessCentralCustomerReader extends AbstractBusinessCentralReader
{
    use CustomerReaderTrait;
}
