<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Operations\VoidBill;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractDeleteModelApiRoute<Bill>
 */
class VoidBillApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(
        private VoidBill $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['vendor_payments.delete'],
            modelClass: Bill::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Bill
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $bill = $this->retrieveModel($context);

        try {
            $this->operation->void($bill);

            return $bill;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
