<?php

namespace App\Integrations\AccountingSync\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Adyen\Models\AdyenAffirmCapture;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\PaymentMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

class RetrievePaymentCaptureRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Payment::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Payment $payment */
        $payment = parent::buildResponse($context);

        if (PaymentMethod::AFFIRM !== $payment->method  && PaymentMethod::KLARNA !== $payment->method) {
            return new JsonResponse();
        }

        $charge = $payment->charge;
        if (!$charge || AdyenGateway::ID !== $charge->gateway || !$charge->merchant_account) {
            return new JsonResponse();
        }

        if (!$flow = $charge->payment_flow) {
            return new JsonResponse();
        }

        return AdyenAffirmCapture::where('payment_flow_id', $flow->id)->oneOrNull();
    }
}
