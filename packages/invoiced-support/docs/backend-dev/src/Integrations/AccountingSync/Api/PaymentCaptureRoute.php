<?php

namespace App\Integrations\AccountingSync\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\Enums\AdyenAffirmCaptureStatus;
use App\Integrations\Adyen\Models\AdyenAffirmCapture;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\UpdateChargeStatus;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class PaymentCaptureRoute extends AbstractRetrieveModelApiRoute implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly AdyenGateway $gateway,
        private readonly UpdateChargeStatus $updateChargeStatus,
    ) {
    }

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
            throw new InvalidRequest("Invalid payment method");
        }

        $charge = $payment->charge;

        if (!$charge || AdyenGateway::ID !== $charge->gateway || !$charge->merchant_account) {
            throw new InvalidRequest("Capture not authorized");
        }

        if (!$flow = $payment->charge?->payment_flow) {
            throw new InvalidRequest("Capture not authorized");
        }

        $reference = RandomString::generate();

        /** @var AdyenAffirmCapture $capture */
        $capture = AdyenAffirmCapture::where('payment_flow_id', $flow->id)->one();

        if (Charge::PENDING === $charge->status) {
            try {
                $this->gateway->capture($charge->merchant_account, $reference, $charge->gateway_id, $payment->getAmount(), $capture->line_items);
            } catch (IntegrationApiException $e) {
                $this->logger->error("Affirm capture failed", ['exception' => $e]);

                throw new InvalidRequest("Capture failed");
            }

            $this->updateChargeStatus->saveStatus($charge, Charge::SUCCEEDED);

            $capture->status = AdyenAffirmCaptureStatus::Captured;
            $capture->payment = $payment;
            $capture->reference = $reference;
            $capture->save();
        }

        return $capture;
    }
}
