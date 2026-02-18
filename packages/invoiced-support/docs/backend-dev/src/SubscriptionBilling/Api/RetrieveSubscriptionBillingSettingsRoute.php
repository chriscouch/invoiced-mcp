<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\SubscriptionBilling\Models\SubscriptionBillingSettings;

/**
 * @extends AbstractRetrieveModelApiRoute<SubscriptionBillingSettings>
 */
class RetrieveSubscriptionBillingSettingsRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: SubscriptionBillingSettings::class,
            features: ['subscription_billing'],
        );
    }

    public function retrieveModel(ApiCallContext $context): SubscriptionBillingSettings
    {
        return $this->tenant->get()->subscription_billing_settings;
    }
}
