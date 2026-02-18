<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Operations\CreateVendorPaymentBatch;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractCreateModelApiRoute<VendorPaymentBatch>
 */
class CreateVendorPaymentBatchRoute extends AbstractCreateModelApiRoute
{
    public function __construct(
        private readonly CreateVendorPaymentBatch $operation
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
                'bank_account' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'card' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'payment_method' => new RequestParameter(
                    types: ['string'],
                    allowedValues: ['ach', 'credit_card', 'echeck', 'print_check'],
                ),
                'bills' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
                'initial_check_number' => new RequestParameter(
                    types: ['integer', 'null'],
                ),
                'check_layout' => new RequestParameter(
                    types: ['string', 'null'],
                ),
            ],
            requiredPermissions: ['vendor_payments.create'],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorPaymentBatch
    {
        try {
            return $this->operation->create($context->requestParameters);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
