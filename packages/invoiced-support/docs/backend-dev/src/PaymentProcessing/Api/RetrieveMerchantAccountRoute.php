<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * @extends AbstractRetrieveModelApiRoute<MerchantAccount>
 */
class RetrieveMerchantAccountRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: MerchantAccount::class,
            features: ['accounts_receivable'],
        );
    }
}
