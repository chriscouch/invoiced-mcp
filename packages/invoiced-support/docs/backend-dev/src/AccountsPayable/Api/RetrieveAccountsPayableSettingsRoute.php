<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\AccountsPayableSettings;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractRetrieveModelApiRoute<AccountsPayableSettings>
 */
class RetrieveAccountsPayableSettingsRoute extends AbstractRetrieveModelApiRoute
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
            modelClass: AccountsPayableSettings::class,
            features: ['accounts_payable'],
        );
    }

    public function retrieveModel(ApiCallContext $context): AccountsPayableSettings
    {
        return $this->tenant->get()->accounts_payable_settings;
    }
}
