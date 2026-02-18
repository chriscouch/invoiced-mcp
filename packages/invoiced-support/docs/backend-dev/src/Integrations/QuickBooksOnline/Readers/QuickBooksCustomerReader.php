<?php

namespace App\Integrations\QuickBooksOnline\Readers;

use App\Integrations\AccountingSync\Traits\CustomerReaderTrait;

class QuickBooksCustomerReader extends AbstractQuickBooksReader
{
    use CustomerReaderTrait;
}
