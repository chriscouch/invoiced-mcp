<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;

class ConvenienceFeeHelper
{
    /**
     * Calculates the convenience fee for a given payment scenario.
     */
    public static function calculate(PaymentMethod $method, Customer $customer, Money $amount): array
    {
        // In order for a convenience fee to apply, it must be
        // enabled on both the payment method and customer.
        $convenienceFeePercent = null;
        $convenienceFeeAmount = null;
        $convenienceFeeTotal = null;
        if ($customer->convenience_fee && $method->convenience_fee > 0) {
            $convenienceFeePercent = $method->getConvenienceFeePercent();
            $convenienceFeeAmount = $amount->percent($convenienceFeePercent);
            $convenienceFeeTotal = $amount->add($convenienceFeeAmount);
        }

        return [
            'percent' => $convenienceFeePercent,
            'amount' => $convenienceFeeAmount,
            'total' => $convenienceFeeTotal,
        ];
    }
}
