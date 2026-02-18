<?php

namespace App\PaymentProcessing\Api\Refunds;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Refund;

/**
 * @extends AbstractListModelsApiRoute<Refund>
 */
class ListRefundsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Refund::class,
            features: ['accounts_receivable'],
        );
    }
}
