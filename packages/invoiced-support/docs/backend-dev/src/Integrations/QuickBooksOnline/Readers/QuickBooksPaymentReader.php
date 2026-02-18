<?php

namespace App\Integrations\QuickBooksOnline\Readers;

use App\Integrations\AccountingSync\Traits\PaymentReaderTrait;

class QuickBooksPaymentReader extends AbstractQuickBooksReader
{
    use PaymentReaderTrait;
}
