<?php

namespace App\PaymentProcessing\Libs;

use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentInstruction;

/**
 * Payment method by country decorator.
 */
class PaymentMethodDecorator
{
    public function decorate(PaymentMethod $paymentMethod, ?string $country): PaymentMethod
    {
        if ($country) {
            /** @var PaymentInstruction|null $paymentInstruction */
            $paymentInstruction = PaymentInstruction::where('payment_method_id', $paymentMethod->id)
                ->where('country', $country)
                ->oneOrNull();
            if (null !== $paymentInstruction) {
                $paymentMethod->meta = $paymentInstruction->meta;
                $paymentMethod->enabled = $paymentInstruction->enabled;
            }
        }

        return $paymentMethod;
    }
}
