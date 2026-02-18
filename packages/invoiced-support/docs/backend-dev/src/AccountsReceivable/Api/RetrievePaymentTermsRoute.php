<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentTerms;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<PaymentTerms>
 */
class RetrievePaymentTermsRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: PaymentTerms::class,
            features: ['accounts_receivable'],
        );
    }
}
