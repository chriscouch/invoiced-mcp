<?php

namespace App\PaymentProcessing\Api\Payouts;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Payout;

/**
 * @extends AbstractListModelsApiRoute<Payout>
 */
class ListPayoutsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Payout::class,
            features: ['accounts_receivable'],
        );
    }
}
