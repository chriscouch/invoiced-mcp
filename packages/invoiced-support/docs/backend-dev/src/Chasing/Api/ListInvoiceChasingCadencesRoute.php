<?php

namespace App\Chasing\Api;

use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<InvoiceChasingCadence>
 */
class ListInvoiceChasingCadencesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: InvoiceChasingCadence::class,
            features: ['invoice_chasing'],
        );
    }
}
