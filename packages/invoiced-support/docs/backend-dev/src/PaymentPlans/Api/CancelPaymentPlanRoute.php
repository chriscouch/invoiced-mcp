<?php

namespace App\PaymentPlans\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class CancelPaymentPlanRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.edit'],
            modelClass: Invoice::class,
            features: ['payment_plans'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $invoice = parent::buildResponse($context);

        $paymentPlan = $invoice->paymentPlan();
        if (!$paymentPlan) {
            throw new InvalidRequest('This invoice does not have a payment plan attached.');
        }

        if ($paymentPlan->cancel()) {
            return new Response('', 204);
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic error
        throw new ApiError('There was an error canceling the payment plan.');
    }
}
