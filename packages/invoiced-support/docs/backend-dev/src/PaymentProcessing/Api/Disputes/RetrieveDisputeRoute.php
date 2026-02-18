<?php

namespace App\PaymentProcessing\Api\Disputes;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\Dispute;

/**
 * @extends AbstractRetrieveModelApiRoute<Dispute>
 */
class RetrieveDisputeRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Dispute::class,
            features: ['accounts_receivable'],
        );
    }
}
