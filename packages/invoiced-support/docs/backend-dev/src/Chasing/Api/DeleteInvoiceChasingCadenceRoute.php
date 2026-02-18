<?php

namespace App\Chasing\Api;

use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<InvoiceChasingCadence>
 */
class DeleteInvoiceChasingCadenceRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: InvoiceChasingCadence::class,
            features: ['invoice_chasing'],
        );
    }
}
