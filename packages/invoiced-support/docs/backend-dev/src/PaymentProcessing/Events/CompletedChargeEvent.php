<?php

namespace App\PaymentProcessing\Events;

use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use Symfony\Contracts\EventDispatcher\Event;

final class CompletedChargeEvent extends Event
{
    public function __construct(
        public readonly ChargeValueObject $chargeValueObject,
        public readonly ChargeApplication $chargeApplication,
        public readonly ?PaymentFlow $paymentFlow,
        public readonly ?string $receiptEmail,
        public readonly Charge $charge,
    ) {
    }
}
