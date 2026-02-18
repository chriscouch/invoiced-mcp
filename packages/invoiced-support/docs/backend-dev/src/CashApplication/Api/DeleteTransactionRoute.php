<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class DeleteTransactionRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['payments.delete'],
            modelClass: Transaction::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $this->retrieveModel($context);

        if ($this->model->payment_id) {
            throw new InvalidRequest('You cannot delete a transaction with an associated payment.');
        }

        return parent::buildResponse($context);
    }
}
