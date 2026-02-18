<?php

namespace App\Companies\Api;

use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Multitenant\TenantContext;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

class EditSamlSettingsRoute extends AbstractModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'cert' => new RequestParameter(required: true),
                'domain' => new RequestParameter(),
                'entity_id' => new RequestParameter(required: true),
                'sso_url' => new RequestParameter(required: true),
                'slo_url' => new RequestParameter(required: true),
                'enabled' => new RequestParameter(
                    types: ['boolean'],
                    default: false
                ),
                'disable_non_sso' => new RequestParameter(
                    types: ['boolean'],
                    default: false
                ),
            ],
            requiredPermissions: ['business.admin'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $company = $this->tenant->get();
        $model = CompanySamlSettings::where('company_id', $company->id)->oneOrNull();
        if ($model) {
            $result = $model->set($context->requestParameters);
        } else {
            $model = new CompanySamlSettings();
            $model->company = $company;
            $result = $model->create($context->requestParameters);
        }

        if (!$result) {
            $this->setModel($model);
            if ($error = $this->getFirstError()) {
                throw $this->modelValidationError($error);
            }
        }

        return $model;
    }
}
