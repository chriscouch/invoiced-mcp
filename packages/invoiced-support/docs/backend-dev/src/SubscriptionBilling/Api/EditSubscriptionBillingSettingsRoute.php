<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\SubscriptionBilling\Models\SubscriptionBillingSettings;

/**
 * @extends AbstractEditModelApiRoute<SubscriptionBillingSettings>
 */
class EditSubscriptionBillingSettingsRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'after_subscription_nonpayment' => new RequestParameter(),
                'subscription_draft_invoices' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: SubscriptionBillingSettings::class,
            features: ['subscription_billing'],
        );
    }

    public function retrieveModel(ApiCallContext $context): SubscriptionBillingSettings
    {
        return $this->tenant->get()->subscription_billing_settings;
    }
}
