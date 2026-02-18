<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;

class CreateTransactionRoute extends AbstractCreateModelApiRoute
{
    use AccountingApiParametersTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['payments.create'],
            modelClass: Transaction::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parseRequestAccountingParameters($context);

        /** @var Transaction $transaction */
        $transaction = parent::buildResponse($context);
        $this->createAccountingMapping($transaction);

        return $transaction;
    }
}
