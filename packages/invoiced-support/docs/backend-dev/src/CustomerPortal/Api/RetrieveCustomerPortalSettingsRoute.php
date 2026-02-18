<?php

namespace App\CustomerPortal\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\CustomerPortal\Models\CustomerPortalSettings;

/**
 * @extends AbstractRetrieveModelApiRoute<CustomerPortalSettings>
 */
class RetrieveCustomerPortalSettingsRoute extends AbstractRetrieveModelApiRoute
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
            modelClass: CustomerPortalSettings::class,
            features: ['billing_portal'],
        );
    }

    public function retrieveModel(ApiCallContext $context): CustomerPortalSettings
    {
        return $this->tenant->get()->customer_portal_settings;
    }
}
