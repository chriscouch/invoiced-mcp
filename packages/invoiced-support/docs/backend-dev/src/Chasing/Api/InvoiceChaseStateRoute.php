<?php

namespace App\Chasing\Api;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Chasing\InvoiceChasing\InvoiceChaseStateCalculator;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class InvoiceChaseStateRoute extends AbstractApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $invoiceId = (int) $context->request->attributes->get('parent_id');

        $delivery = InvoiceDelivery::where('invoice_id', $invoiceId)->oneOrNull();
        if (!($delivery instanceof InvoiceDelivery)) {
            return [];
        }

        return InvoiceChaseStateCalculator::getState($delivery);
    }
}
