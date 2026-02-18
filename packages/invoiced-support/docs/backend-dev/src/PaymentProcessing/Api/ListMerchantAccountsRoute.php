<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * @extends AbstractListModelsApiRoute<MerchantAccount>
 */
class ListMerchantAccountsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: MerchantAccount::class,
            filterableProperties: ['deleted'],
            features: ['accounts_receivable'],
        );
    }
}
