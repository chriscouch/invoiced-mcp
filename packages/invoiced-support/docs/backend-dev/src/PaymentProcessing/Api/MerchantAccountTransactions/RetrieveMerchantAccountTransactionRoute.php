<?php

namespace App\PaymentProcessing\Api\MerchantAccountTransactions;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\MerchantAccountTransaction;

/**
 * @extends AbstractRetrieveModelApiRoute<MerchantAccountTransaction>
 */
class RetrieveMerchantAccountTransactionRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: MerchantAccountTransaction::class,
            features: ['accounts_receivable'],
        );
    }
}
