<?php

namespace App\PaymentProcessing\ValueObjects;

use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Interfaces\CreditChargeApplicationItemInterface;
use App\PaymentProcessing\Models\PaymentFlowApplication;

final class CreditChargeApplicationItem extends AbstractChargeApplicationItem implements CreditChargeApplicationItemInterface
{
    public function build(): array
    {
        return [
            'type' => PaymentItemType::Credit->value,
            'amount' => $this->amount->toDecimal(),
        ];
    }

    public function buildApplication(): PaymentFlowApplication
    {
        $application = new PaymentFlowApplication();
        $application->type = PaymentItemIntType::Credit;
        $application->amount = $this->amount->toDecimal();

        return $application;
    }

    public function getCredit(): Money
    {
        return $this->amount;
    }
}
