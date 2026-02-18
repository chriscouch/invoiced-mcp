<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\CreateVendorPayment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractCreateModelApiRoute<VendorPayment>
 */
class CreateVendorPaymentApiRoute extends AbstractCreateModelApiRoute
{
    public function __construct(
        private CreateVendorPayment $operation,
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
                'date' => new RequestParameter(
                    types: ['string'],
                ),
                'payment_method' => new RequestParameter(
                    types: ['string'],
                ),
                'reference' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'notes' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'applied_to' => new RequestParameter(
                    types: ['array'],
                ),
            ],
            requiredPermissions: ['vendor_payments.create'],
            modelClass: VendorPayment::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorPayment
    {
        try {
            $requestParameters = $context->requestParameters;
            $items = $requestParameters['applied_to'];
            unset($requestParameters['applied_to']);

            return $this->operation->create($requestParameters, $items);
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
