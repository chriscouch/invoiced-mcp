<?php

namespace App\Companies\Onboarding;

use App\Companies\Enums\PhoneVerificationChannel;
use App\Companies\Enums\VerificationStatus;
use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyPhoneNumber;
use App\Companies\Verification\PhoneVerification;
use Symfony\Component\HttpFoundation\Request;

class PhoneVerificationStartStep implements OnboardingStepInterface
{
    public function __construct(
        private PhoneVerification $phoneVerification,
        private string $environment,
    ) {
    }

    public function canRevisit(Company $company): bool
    {
        // Phone verification does not happen in sandbox
        if ('sandbox' == $this->environment) {
            return false;
        }

        return VerificationStatus::Verified != CompanyPhoneNumber::getVerificationStatus($company);
    }

    public function mustPerform(Company $company): bool
    {
        // Phone verification does not happen in sandbox
        if ('sandbox' == $this->environment) {
            return false;
        }

        return VerificationStatus::NotVerified == CompanyPhoneNumber::getVerificationStatus($company);
    }

    public function handleSubmit(Company $company, Request $request): void
    {
        $countryCode = $request->request->getString('country_code');
        $phone = $request->request->getString('phone');
        $channelId = $request->request->getInt('channel');
        if (!$channelId) {
            throw new OnboardingException('Please select how you would like to verify your phone number.', 'channel');
        }
        $channel = PhoneVerificationChannel::from($channelId);

        try {
            $this->phoneVerification->start($company, $countryCode, $phone, $channel);
        } catch (BusinessVerificationException $e) {
            throw new OnboardingException($e->getMessage(), 'phone');
        }
    }

    public function setPhoneVerification(PhoneVerification $phoneVerification): void
    {
        $this->phoneVerification = $phoneVerification;
    }
}
