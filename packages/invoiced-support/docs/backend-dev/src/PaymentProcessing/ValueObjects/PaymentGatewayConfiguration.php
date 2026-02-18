<?php

namespace App\PaymentProcessing\ValueObjects;

final readonly class PaymentGatewayConfiguration
{
    public function __construct(
        public string $gateway,
        public object $credentials,
    ) {
    }
}
