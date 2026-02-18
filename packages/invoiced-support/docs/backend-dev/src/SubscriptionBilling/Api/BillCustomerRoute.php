<?php

namespace App\SubscriptionBilling\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Exception;

class BillCustomerRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['invoices.create'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $invoice = new Invoice();
        $invoice->customer = (int) $context->request->attributes->get('customer_id');
        $this->setModel($invoice);

        try {
            $this->model->withPending(true);
        } catch (Exception $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return parent::buildResponse($context);
    }
}
