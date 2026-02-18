<?php

namespace App\PaymentProcessing\Api\Charges;

use App\Core\I18n\ValueObjects\Money;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Operations\ProcessRefund;
use Symfony\Component\HttpFoundation\Response;

class RefundChargeRoute extends AbstractRetrieveModelApiRoute
{
    private float $amount;

    public function __construct(private ProcessRefund $processRefund)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['refunds.create'],
            modelClass: Charge::class,
            features: ['accounts_receivable'],
        );
    }

    /**
     * Sets the refund amount.
     */
    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * Gets the refund amount.
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->setAmount((float) $context->request->request->get('amount'));

        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        /** @var Charge $charge */
        $charge = $this->retrieveModel($context);

        $amount = Money::fromDecimal($charge->currency, $this->amount);

        try {
            $refund = $this->processRefund->refund($charge, $amount);

            if (null === $refund) {
                return new Response('', 204);
            }

            return $refund;
        } catch (RefundException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }

    public function getSuccessfulResponse(): Response
    {
        return new Response('', 201);
    }
}
