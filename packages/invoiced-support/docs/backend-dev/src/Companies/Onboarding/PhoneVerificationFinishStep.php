<?php

namespace App\Companies\Onboarding;

use App\Companies\Enums\VerificationStatus;
use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Verification\PhoneVerification;
use Symfony\Component\HttpFoundation\Request;

class PhoneVerificationFinishStep implements OnboardingStepInterface
{
    public function __construct(
        private PhoneVerification $phoneVerification,
        private string $environment,
    ) {
    }

    public function canRevisit(Company $company): bool
    {
        return $this->mustPerform($company);
    }

    public function mustPerform(Company $company): bool
    {
        // Phone verification does not happen in sandbox
        if ('sandbox' == $this->environment) {
            return false;
        }

        return VerificationStatus::Pending == CompanyPhoneNumber::getVerificationStatus($company);
    }

    public function handleSubmit(Company $company, Request $request): void
    {
        $companyPhone = CompanyPhoneNumber::where('verified_at IS NULL')
            ->sort('id DESC')
            ->oneOrNull();
        if (!$companyPhone) {
            throw new OnboardingException('Could not find a pending phone number verification');
        }

        try {
            $this->phoneVerification->complete($companyPhone, $request->request->getString('code'));
        } catch (BusinessVerificationException $e) {
            throw new OnboardingException($e->getMessage(), 'code');
        }
    }

    public function setPhoneVerification(PhoneVerification $phoneVerification): void
    {
        $this->phoneVerification = $phoneVerification;
    }
}
