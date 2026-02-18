<?php

namespace App\PaymentPlans\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class RetrievePaymentPlanRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Invoice::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $invoice = parent::buildResponse($context);

        $paymentPlan = $invoice->paymentPlan();
        if (!$paymentPlan) {
            throw new InvalidRequest('This invoice does not have a payment plan attached.');
        }

        return $paymentPlan;
    }
}
