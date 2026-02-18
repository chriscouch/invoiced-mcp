<?php

namespace App\PaymentProcessing\ValueObjects;

use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Enums\PaymentItemType;
use App\PaymentProcessing\Models\PaymentFlowApplication;

final class ConvenienceFeeChargeApplicationItem extends AbstractChargeApplicationItem
{
    public function build(): array
    {
        return [
            'type' => PaymentItemType::ConvenienceFee->value,
            'amount' => $this->amount->toDecimal(),
        ];
    }

    public function buildApplication(): PaymentFlowApplication
    {
        $application = new PaymentFlowApplication();
        $application->type = PaymentItemIntType::ConvenienceFee;
        $application->amount = $this->amount->toDecimal();

        return $application;
    }
}
