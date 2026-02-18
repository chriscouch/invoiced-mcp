<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\AccountsReceivableSettings;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;

/**
 * @extends AbstractRetrieveModelApiRoute<AccountsReceivableSettings>
 */
class RetrieveAccountsReceivableSettingsRoute extends AbstractRetrieveModelApiRoute
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
            modelClass: AccountsReceivableSettings::class,
            features: ['accounts_receivable'],
        );
    }

    public function retrieveModel(ApiCallContext $context): AccountsReceivableSettings
    {
        return $this->tenant->get()->accounts_receivable_settings;
    }
}
