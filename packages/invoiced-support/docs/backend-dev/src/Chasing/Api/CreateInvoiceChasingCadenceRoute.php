<?php

namespace App\Chasing\Api;

use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractCreateModelApiRoute<InvoiceChasingCadence>
 */
class CreateInvoiceChasingCadenceRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: InvoiceChasingCadence::class,
            features: ['invoice_chasing'],
        );
    }
}
