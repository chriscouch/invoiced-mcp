<?php

namespace App\PaymentProcessing\ValueObjects;

final readonly class PaymentFormCapabilities
{
    public function __construct(
        public bool $isSubmittable,
        public bool $supportsVaulting,
        public bool $supportsConvenienceFee,
        public bool $hasReceiptEmail,
    ) {
    }
}
