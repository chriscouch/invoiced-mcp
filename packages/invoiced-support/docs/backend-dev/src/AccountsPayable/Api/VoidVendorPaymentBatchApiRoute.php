<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Operations\VoidVendorPaymentBatch;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractDeleteModelApiRoute<VendorPaymentBatch>
 */
class VoidVendorPaymentBatchApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(
        private VoidVendorPaymentBatch $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['vendor_payments.delete'],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorPaymentBatch
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
