<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\PaymentTerms;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<PaymentTerms>
 */
class DeletePaymentTermsRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: PaymentTerms::class,
            features: ['accounts_receivable'],
        );
    }
}
