<?php

namespace App\Companies\Api;

use App\Companies\Models\Company;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractEditModelApiRoute<Company>
 */
class EditCompanyRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'address_extra' => new RequestParameter(),
                'currency' => new RequestParameter(),
                'custom_domain' => new RequestParameter(),
                'date_format' => new RequestParameter(),
                'email' => new RequestParameter(),
                'highlight_color' => new RequestParameter(),
                'language' => new RequestParameter(),
                'logo' => new RequestParameter(),
                'nickname' => new RequestParameter(),
                'phone' => new RequestParameter(),
                'show_currency_code' => new RequestParameter(),
                'tax_id' => new RequestParameter(),
                'time_zone' => new RequestParameter(),
                'username' => new RequestParameter(),
                'website' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: Company::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): Company
    {
        $company = parent::retrieveModel($context);

        // Validate tenant ID matches context
        if ($this->tenant->get()->id != $company->id) {
            throw $this->modelNotFoundError();
        }

        return $company;
    }
}
