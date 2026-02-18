<?php

namespace App\Companies\Onboarding;

use App\Companies\Exception\BusinessVerificationException;
use App\Companies\Exception\OnboardingException;
use App\Companies\Interfaces\OnboardingStepInterface;
use App\Companies\Models\Company;
use App\Companies\Models\CompanyAddress;
use App\Companies\Verification\AddressVerification;
use Carbon\CarbonImmutable;
use CommerceGuys\Addressing\Address;
use App\Core\Orm\Exception\ModelException;
use Symfony\Component\HttpFoundation\Request;

class CompanyInfoStep implements OnboardingStepInterface
{
    public function __construct(
        private AddressVerification $addressVerification,
    ) {
    }

    public function mustPerform(Company $company): bool
    {
        return !$company->name || !$company->address1 || !$company->country || !$company->industry;
    }

    public function canRevisit(Company $company): bool
    {
        return true;
    }

    public function handleSubmit(Company $company, Request $request): void
    {
        try {
            $country = ((string) $request->request->get('country')) ?: (string) $company->country;
            if (2 != strlen($country)) {
                throw new OnboardingException('Missing country');
            }

            $companyName = (string) $request->request->get('company_name');
            $dbaName = null;
            if ('1' == (string) $request->request->get('has_dba')) {
                $dbaName = (string) $request->request->get('nickname');
            }
            if ($companyName == $dbaName) {
                throw new OnboardingException('DBA name cannot be the same as the legal name');
            }

            // Verify the address if a live company
            if ($this->addressVerification->countryIsSupported($country) && !$company->test_mode) {
                // Only verify if this is a new address for the company
                $existingAddress = CompanyAddress::where('address1', $request->request->get('address1'))
                    ->where('address2', $request->request->get('address2'))
                    ->where('city', $request->request->get('city'))
                    ->where('state', $request->request->get('state'))
                    ->where('postal_code', $request->request->get('postal_code'))
                    ->where('country', $country)
                    ->count();

                if (!$existingAddress) {
                    $address = new Address(
                        countryCode: $country,
                        administrativeArea: (string) $request->request->get('state'),
                        locality: (string) $request->request->get('city'),
                        postalCode: (string) $request->request->get('postal_code'),
                        addressLine1: (string) $request->request->get('address1'),
                        addressLine2: (string) $request->request->get('address2')
                    );
                    $this->addressVerification->validate($address);

                    // Create the company address record
                    $companyAddress = new CompanyAddress();
                    $companyAddress->address1 = (string) $request->request->get('address1');
                    $companyAddress->address2 = (string) $request->request->get('address2');
                    $companyAddress->city = (string) $request->request->get('city');
                    $companyAddress->state = (string) $request->request->get('state');
                    $companyAddress->postal_code = (string) $request->request->get('postal_code');
                    $companyAddress->country = $country;
                    $companyAddress->verified_at = CarbonImmutable::now();
                    $companyAddress->saveOrFail();
                }
            }

            // Store the updated values on the company
            $company->name = $companyName;
            $company->nickname = $dbaName;

            $company->address1 = (string) $request->request->get('address1');
            $company->address2 = (string) $request->request->get('address2');
            $company->city = (string) $request->request->get('city');
            $company->state = (string) $request->request->get('state');
            $company->postal_code = (string) $request->request->get('postal_code');
            if ($country != $company->country) {
                $company->country = $country;
            }
            $company->industry = (string) $request->request->get('industry');
            $company->saveOrFail();

            // Update the name of the billing profile
            $billingProfile = $company->billing_profile;
            if ($billingProfile && !$billingProfile->name) {
                $billingProfile->name = $company->name;
                $billingProfile->saveOrFail();
            }
        } catch (BusinessVerificationException|ModelException $e) {
            throw new OnboardingException($e->getMessage(), 'address');
        }
    }
}
