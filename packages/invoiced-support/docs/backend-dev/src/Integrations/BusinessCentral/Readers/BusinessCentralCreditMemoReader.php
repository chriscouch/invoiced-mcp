<?php

namespace App\Integrations\BusinessCentral\Readers;

use App\Integrations\AccountingSync\Traits\CreditNoteReaderTrait;

class BusinessCentralCreditMemoReader extends AbstractBusinessCentralReader
{
    use CreditNoteReaderTrait;
}
