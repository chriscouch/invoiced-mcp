<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\AccountsPayableSettings;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractEditModelApiRoute<AccountsPayableSettings>
 */
class EditAccountsPayableSettingsRoute extends AbstractEditModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'aging_buckets' => new RequestParameter(),
                'aging_date' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: AccountsPayableSettings::class,
            features: ['accounts_payable'],
        );
    }

    public function retrieveModel(ApiCallContext $context): AccountsPayableSettings
    {
        return $this->tenant->get()->accounts_payable_settings;
    }
}
