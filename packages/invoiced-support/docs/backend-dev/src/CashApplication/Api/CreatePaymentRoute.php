<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;

class CreatePaymentRoute extends AbstractCreateModelApiRoute
{
    use AccountingApiParametersTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'amount' => new RequestParameter(
                    required: true,
                    types: ['numeric'],
                ),
                'currency' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'customer' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'date' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'method' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'reference' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'source' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'notes' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'ach_sender_id' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'attachments' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'applied_to' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'metadata' => new RequestParameter(
                    types: ['array', 'null'],
                ),
                'accounting_id' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'accounting_system' => new RequestParameter(
                    types: ['string', 'null'],
                ),
            ],
            requiredPermissions: ['payments.create'],
            modelClass: Payment::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parseRequestAccountingParameters($context);

        /** @var Payment $payment */
        $payment = parent::buildResponse($context);

        if (isset($context->requestParameters['applied_to'])) {
            foreach ($context->requestParameters['applied_to'] as $line) {
                if (!isset($line['type'])) {
                    throw new InvalidRequest('Missing `type` parameter', 400, 'applied_to');
                }

                if (!isset($line['amount'])) {
                    throw new InvalidRequest('Missing `amount` parameter', 400, 'applied_to');
                }
            }
        }

        $this->createAccountingMapping($payment);

        return $payment;
    }
}
