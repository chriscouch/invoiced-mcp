<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Invoice>
 */
class DeleteInvoiceRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['invoices.delete'],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }
}
