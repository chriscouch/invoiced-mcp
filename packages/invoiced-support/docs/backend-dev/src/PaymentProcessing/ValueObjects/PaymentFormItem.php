<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Enums\PaymentAmountOption;

final class PaymentFormItem
{
    public function __construct(
        public readonly Money $amount,
        public readonly string $description = '',
        public readonly ?ReceivableDocument $document = null,
        public readonly ?PaymentAmountOption $amountOption = null
    ) {
    }
}
