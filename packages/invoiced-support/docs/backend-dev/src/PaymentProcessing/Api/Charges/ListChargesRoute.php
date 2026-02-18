<?php

namespace App\PaymentProcessing\Api\Charges;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Charge;

/**
 * @extends AbstractListModelsApiRoute<Charge>
 */
class ListChargesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Charge::class,
            features: ['accounts_receivable'],
        );
    }
}
