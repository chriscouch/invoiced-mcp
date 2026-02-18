<?php

namespace App\PaymentProcessing\Operations;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Models\CustomerPaymentBatch;

/**
 * @extends AbstractDeleteModelApiRoute<CustomerPaymentBatch>
 */
class VoidCustomerPaymentBatchApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(
        private VoidCustomerPaymentBatch $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['payments.delete'],
            modelClass: CustomerPaymentBatch::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): CustomerPaymentBatch
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $payment = $this->retrieveModel($context);

        try {
            $this->operation->void($payment);

            return $payment;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
