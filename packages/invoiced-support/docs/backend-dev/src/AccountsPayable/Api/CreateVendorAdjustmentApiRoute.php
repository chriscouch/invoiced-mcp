<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorAdjustment;
use App\AccountsPayable\Operations\CreateVendorAdjustment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractCreateModelApiRoute<VendorAdjustment>
 */
class CreateVendorAdjustmentApiRoute extends AbstractCreateModelApiRoute
{
    public function __construct(
        private CreateVendorAdjustment $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'vendor' => new RequestParameter(
                    required: true,
                    types: ['int'],
                ),
                'amount' => new RequestParameter(
                    required: true,
                    types: ['float', 'integer'],
                ),
                'currency' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'bill' => new RequestParameter(
                    types: ['int'],
                ),
                'vendor_credit' => new RequestParameter(
                    types: ['int'],
                ),
                'date' => new RequestParameter(
                    types: ['string'],
                ),
                'notes' => new RequestParameter(
                    types: ['string', 'null'],
                ),
            ],
            requiredPermissions: ['vendor_payments.create'],
            modelClass: VendorAdjustment::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorAdjustment
    {
        try {
            return $this->operation->create($context->requestParameters);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
