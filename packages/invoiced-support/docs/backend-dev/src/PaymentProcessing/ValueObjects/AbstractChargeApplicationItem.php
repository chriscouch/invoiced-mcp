<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsPayable\Models\PayableDocument;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Interfaces\ChargeApplicationItemInterface;

abstract class AbstractChargeApplicationItem implements ChargeApplicationItemInterface
{
    public function __construct(
        protected Money $amount,
        protected ?ReceivableDocument $document = null,
        private ?PayableDocument $payableDocument = null,
    ) {
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDocument(): ?ReceivableDocument
    {
        return $this->document;
    }

    public function getPayableDocument(): ?PayableDocument
    {
        return $this->payableDocument;
    }
}
