<?php

namespace App\CashApplication\Api;

use App\AccountsReceivable\Api\VoidDocumentRoute;
use App\CashApplication\Models\Payment;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class VoidPaymentRoute extends VoidDocumentRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['payments.delete'],
            modelClass: Payment::class,
            features: ['accounts_receivable'],
        );
    }
}
