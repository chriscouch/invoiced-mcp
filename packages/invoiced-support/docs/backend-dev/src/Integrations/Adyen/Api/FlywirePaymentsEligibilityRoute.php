<?php

namespace App\Integrations\Adyen\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\FlywirePaymentsOnboarding;
use App\Integrations\Adyen\Models\AdyenAccount;

class FlywirePaymentsEligibilityRoute extends AbstractApiRoute
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly FlywirePaymentsOnboarding $onboarding,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $company = $this->tenant->get();
        $onboardingUrl = null;
        $isEligible = $this->onboarding->isEligible($company);
        $alreadyEnrolled = false;
        $pricing = null;
        $isActivated = false;
        $hasOnboardingProblem = false;

        if ($isEligible) {
            $alreadyEnrolled = $this->onboarding->isAlreadyEnrolled($company);
            $onboardingUrl = $this->onboarding->getOnboardingStartUrl($company);
            $adyenAccount = AdyenAccount::oneOrNull() ?? new AdyenAccount();
            $isActivated = null != $adyenAccount->activated_at;
            $hasOnboardingProblem = $adyenAccount->has_onboarding_problem;

            // Determine pricing
            $pricingConfig = AdyenConfiguration::getPricingForAccount(true, $adyenAccount); // live mode doesn't matter
            $pricing = [
                'card' => [
                    'variable_fee' => $pricingConfig->card_variable_fee,
                    'international_added_variable_fee' => $pricingConfig->card_international_added_variable_fee,
                    'fixed_fee' => $pricingConfig->card_fixed_fee,
                    'interchange_passthrough' => $pricingConfig->card_interchange_passthrough,
                    'amex_interchange_variable_markup' => $pricingConfig->amex_interchange_variable_markup,
                ],
                'ach' => [
                    'fixed_fee' => $pricingConfig->ach_fixed_fee,
                    'variable_fee' => $pricingConfig->ach_variable_fee,
                    'max_fee' => $pricingConfig->ach_max_fee,
                ],
                'chargeback_fee' => $pricingConfig->chargeback_fee,
            ];
        }

        return [
            'eligible' => $isEligible && !$alreadyEnrolled,
            'onboarding_url' => $onboardingUrl,
            'activated' => $isActivated,
            'has_onboarding_problem' => $hasOnboardingProblem,
            'pricing' => $pricing,
            'deprecated' => $company->features->has('flywire_mor_target') || 'US' == $company->country, // TODO: other countries are not currently configured with Adyen
            'onNotificationList' => false,
        ];
    }
}
