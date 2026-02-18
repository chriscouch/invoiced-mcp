<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Operations\VoidVendorCredit;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractDeleteModelApiRoute<VendorCredit>
 */
class VoidVendorCreditApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(
        private VoidVendorCredit $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['bills.delete'],
            modelClass: VendorCredit::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorCredit
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $vendorCredit = $this->retrieveModel($context);

        try {
            $this->operation->void($vendorCredit);

            return $vendorCredit;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
