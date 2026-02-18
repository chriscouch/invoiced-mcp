<?php

namespace App\Integrations\FreshBooks\Readers;

use App\Integrations\AccountingSync\Traits\CustomerReaderTrait;

class FreshBooksClientReader extends AbstractFreshBooksReader
{
    use CustomerReaderTrait;
}
