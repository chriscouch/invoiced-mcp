<?php

namespace App\Companies\Api;

use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<CompanySamlSettings>
 */
class RetrieveSamlSettingsRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['business.admin'],
            modelClass: CompanySamlSettings::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): CompanySamlSettings
    {
        $company = $this->tenant->get();
        $id = $company->id;
        $settings = CompanySamlSettings::where('company_id', $id)->oneOrNull();
        $this->model = $settings ?? new CompanySamlSettings();

        return $this->model;
    }
}
