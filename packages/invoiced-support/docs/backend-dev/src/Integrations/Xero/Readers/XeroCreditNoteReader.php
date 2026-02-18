<?php

namespace App\Integrations\Xero\Readers;

use App\Integrations\AccountingSync\Traits\CreditNoteReaderTrait;

class XeroCreditNoteReader extends AbstractXeroReader
{
    use CreditNoteReaderTrait;
}
