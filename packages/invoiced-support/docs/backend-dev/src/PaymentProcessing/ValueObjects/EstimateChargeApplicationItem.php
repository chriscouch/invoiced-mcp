<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\Estimate;
use App\CashApplication\Enums\PaymentItemIntType;
use App\CashApplication\Enums\PaymentItemType;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentFlowApplication;

/**
 * @property Estimate $document
 */
final class EstimateChargeApplicationItem extends AbstractChargeApplicationItem
{
    public function __construct(Money $amount, Estimate $estimate)
    {
        parent::__construct($amount, $estimate);
    }

    public function build(): array
    {
        return [
            'type' => PaymentItemType::Estimate->value,
            'estimate' => $this->document,
            'amount' => $this->amount->toDecimal(),
        ];
    }

    public function buildApplication(): PaymentFlowApplication
    {
        $application = new PaymentFlowApplication();
        $application->type = PaymentItemIntType::Estimate;
        $application->estimate = $this->document;
        $application->amount = $this->amount->toDecimal();

        return $application;
    }
}
