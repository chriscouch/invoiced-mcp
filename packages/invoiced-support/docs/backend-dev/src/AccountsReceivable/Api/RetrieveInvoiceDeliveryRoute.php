<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Model;

class RetrieveInvoiceDeliveryRoute extends AbstractModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Model
    {
        $invoiceId = (int) $context->request->attributes->get('parent_id');

        // find invoice delivery
        $delivery = InvoiceDelivery::where('invoice_id', $invoiceId)->oneOrNull();
        if (!$delivery instanceof InvoiceDelivery) {
            $delivery = new InvoiceDelivery();
        }

        return $delivery;
    }
}
