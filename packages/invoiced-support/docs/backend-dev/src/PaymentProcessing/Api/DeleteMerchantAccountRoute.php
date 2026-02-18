<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\MerchantAccount;

/**
 * @extends AbstractDeleteModelApiRoute<MerchantAccount>
 */
class DeleteMerchantAccountRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: MerchantAccount::class,
            features: ['accounts_receivable'],
        );
    }
}
