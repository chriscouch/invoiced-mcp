<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\AccountsReceivable\Models\ReceivableDocument;

final class InvoicedDocument
{
    public function __construct(
        public readonly ReceivableDocument $document,
        public readonly ?AbstractMapping $mapping = null,
    ) {
    }
}
