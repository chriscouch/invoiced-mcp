<?php

namespace App\PaymentProcessing\ValueObjects;

use App\Companies\Models\Company;

class PaymentFormSettings
{
    public function __construct(
        public readonly Company $company,
        public readonly bool $allowPartialPayments,
        public readonly bool $allowApplyingCredits,
        public readonly bool $allowAdvancePayments,
        public readonly bool $allowAutoPayEnrollment,
    ) {
    }
}
