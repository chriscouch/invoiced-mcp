<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Flywire\Models\FlywirePayment;
use App\Themes\Traits\PdfApiTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractRetrieveModelApiRoute<Payment>
 */
class RetrievePaymentRoute extends AbstractRetrieveModelApiRoute
{
    use PdfApiTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Payment::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Payment|Response
    {
        $payment = parent::buildResponse($context);
        $payment = $this->addSurchargePercentage($payment);

        // Return PDF if application/pdf is requested
        if ($this->shouldReturnPdf($context->request)) {
            $locale = $payment->customer()?->getLocale() ?? $payment->tenant()->getLocale();

            return $this->buildResponsePdf($payment, $locale);
        }

        return $payment;
    }

    private function addSurchargePercentage(Payment $payment): Payment
    {
        // let's add surcharge percentage if there was any
        $flywirePayment = FlywirePayment::where('payment_id', $payment->id)
            ->oneOrNull();

        if ($flywirePayment && $flywirePayment->surcharge_percentage > 0)
            $payment->setSurchargePercentage($flywirePayment->surcharge_percentage);

        return $payment;
    }
}
