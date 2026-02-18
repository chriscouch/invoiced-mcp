<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentTerms;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractCreateModelApiRoute<PaymentTerms>
 */
class CreatePaymentTermsRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: PaymentTerms::class,
            features: ['accounts_receivable'],
        );
    }
}
