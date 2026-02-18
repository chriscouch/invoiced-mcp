<?php

namespace App\Chasing\Api;

use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<InvoiceChasingCadence>
 */
class RetrieveInvoiceChasingCadenceRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: InvoiceChasingCadence::class,
            features: ['invoice_chasing'],
        );
    }
}
