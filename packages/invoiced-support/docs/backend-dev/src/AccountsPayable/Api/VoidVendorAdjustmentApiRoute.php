<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorAdjustment;
use App\AccountsPayable\Operations\VoidVendorAdjustment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractDeleteModelApiRoute<VendorAdjustment>
 */
class VoidVendorAdjustmentApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(
        private VoidVendorAdjustment $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['vendor_payments.delete'],
            modelClass: VendorAdjustment::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorAdjustment
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $adjustment = $this->retrieveModel($context);

        try {
            $this->operation->void($adjustment);

            return $adjustment;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
