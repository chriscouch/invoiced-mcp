<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\CashApplicationSettings;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractEditModelApiRoute<CashApplicationSettings>
 */
class EditCashApplicationSettingsRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'short_pay_units' => new RequestParameter(),
                'short_pay_amount' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CashApplicationSettings::class,
            features: ['cash_application'],
        );
    }

    public function retrieveModel(ApiCallContext $context): CashApplicationSettings
    {
        return $this->tenant->get()->cash_application_settings;
    }
}
