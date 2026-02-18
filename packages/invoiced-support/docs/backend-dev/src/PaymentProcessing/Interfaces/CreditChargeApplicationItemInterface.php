<?php

namespace App\PaymentProcessing\Interfaces;

use App\Core\I18n\ValueObjects\Money;

interface CreditChargeApplicationItemInterface
{
    public function getCredit(): Money;
}
