<?php

namespace App\Integrations\QuickBooksOnline\Readers;

use App\Integrations\AccountingSync\Traits\CreditNoteReaderTrait;

class QuickBooksCreditMemoReader extends AbstractQuickBooksReader
{
    use CreditNoteReaderTrait;
}
