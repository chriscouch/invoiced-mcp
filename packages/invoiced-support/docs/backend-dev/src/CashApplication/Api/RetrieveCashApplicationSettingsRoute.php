<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationSettings;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractRetrieveModelApiRoute<CashApplicationSettings>
 */
class RetrieveCashApplicationSettingsRoute extends AbstractRetrieveModelApiRoute
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
            modelClass: CashApplicationSettings::class,
            features: ['cash_application'],
        );
    }

    public function retrieveModel(ApiCallContext $context): CashApplicationSettings
    {
        return $this->tenant->get()->cash_application_settings;
    }
}
