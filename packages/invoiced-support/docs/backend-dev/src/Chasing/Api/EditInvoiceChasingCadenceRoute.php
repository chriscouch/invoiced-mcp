<?php

namespace App\Chasing\Api;

use App\Chasing\Models\InvoiceChasingCadence;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<InvoiceChasingCadence>
 */
class EditInvoiceChasingCadenceRoute extends AbstractEditModelApiRoute
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
