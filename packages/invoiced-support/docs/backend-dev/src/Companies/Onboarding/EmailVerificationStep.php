<?php

namespace App\Companies\Onboarding;

use App\Companies\Enums\VerificationStatus;
use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyEmailAddress;
use App\Companies\Verification\EmailVerification;
use Symfony\Component\HttpFoundation\Request;

class EmailVerificationStep implements OnboardingStepInterface
{
    public function __construct(private EmailVerification $emailVerification)
    {
    }

    public function canRevisit(Company $company): bool
    {
        return $this->mustPerform($company);
    }

    public function mustPerform(Company $company): bool
    {
        return VerificationStatus::Verified != CompanyEmailAddress::getVerificationStatus($company);
    }

    public function handleSubmit(Company $company, Request $request): void
    {
        // Verify the code
        $email = $request->request->get('email');
        if (!$email) {
            throw new OnboardingException('Missing email address');
        }

        $companyEmail = CompanyEmailAddress::queryWithTenant($company)
            ->where('email', $email)
            ->oneOrNull();
        if (!$companyEmail) {
            throw new OnboardingException('Could not find a pending email address verification');
        }

        if (!hash_equals($companyEmail->code, (string) $request->request->get('code'))) {
            throw new OnboardingException('The given code does not match the one sent to your email address. Please try again.');
        }

        $this->emailVerification->complete($companyEmail);
    }
}
