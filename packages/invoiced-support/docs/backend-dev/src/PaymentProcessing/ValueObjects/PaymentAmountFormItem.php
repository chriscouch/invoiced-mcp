<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\ReceivableDocument;

final class PaymentAmountFormItem
{
    public function __construct(
        public readonly array $options,
        public readonly ?ReceivableDocument $document,
        public readonly ?string $nonDocumentType,
    ) {
    }
}
