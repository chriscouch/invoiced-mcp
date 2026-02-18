<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;

class EditTransactionRoute extends AbstractEditModelApiRoute
{
    use AccountingApiParametersTrait;

    private static array $permittedPaymentEditParameters = [
        'metadata',
    ];

    private static array $prohibitedEditParameters = [
        'parent_transaction',
    ];

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['payments.edit'],
            modelClass: Transaction::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parseRequestAccountingParameters($context);

        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $this->retrieveModel($context);

        $requestParameters = $context->requestParameters;

        foreach (array_keys($requestParameters) as $parameter) {
            if (in_array($parameter, self::$prohibitedEditParameters)) {
                unset($requestParameters[$parameter]);
            }
        }

        $context = $context->withRequestParameters($requestParameters);

        if ($this->model->payment_id) {
            foreach (array_keys($context->requestParameters) as $parameter) {
                if (!in_array($parameter, self::$permittedPaymentEditParameters)) {
                    throw new InvalidRequest('You cannot edit a transaction with an associated payment.');
                }
            }
        }

        $transaction = parent::buildResponse($context);
        $this->createAccountingMapping($transaction);

        return $transaction;
    }
}
