<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentTerms;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<PaymentTerms>
 */
class ListPaymentTermsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PaymentTerms::class,
            features: ['accounts_receivable'],
        );
    }
}
