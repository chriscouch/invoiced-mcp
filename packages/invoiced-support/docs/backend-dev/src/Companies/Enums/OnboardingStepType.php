<?php

namespace App\Companies\Enums;

enum OnboardingStepType: string
{
    case VerifyEmail = 'verify-email';
    case VerifyPhoneStart = 'verify-phone-start';
    case VerifyPhoneFinish = 'verify-phone-finish';
    case BusinessType = 'business-type';
    case CompanyInfo = 'company-info';
    case TaxId = 'tax-id';
}
