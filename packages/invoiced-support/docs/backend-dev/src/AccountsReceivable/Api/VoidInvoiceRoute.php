<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class VoidInvoiceRoute extends VoidDocumentRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.void'],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }
}
