<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Models\CustomerPaymentBatch;
use App\PaymentProcessing\Operations\CreateCustomerPaymentBatch;

/**
 * @extends AbstractCreateModelApiRoute<CustomerPaymentBatch>
 */
class CreateCustomerPaymentBatchRoute extends AbstractCreateModelApiRoute
{
    public function __construct(
        private readonly CreateCustomerPaymentBatch $operation
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'number' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'currency' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'payment_method' => new RequestParameter(
                    types: ['string'],
                    allowedValues: ['ach'],
                ),
                'ach_file_format' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'charges' => new RequestParameter(
                    types: ['array', 'null'],
                ),
            ],
            requiredPermissions: ['charges.create'],
            modelClass: CustomerPaymentBatch::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): CustomerPaymentBatch
    {
        try {
            return $this->operation->create($context->requestParameters);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
