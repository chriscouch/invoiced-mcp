<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPayment;
use App\AccountsPayable\Operations\EditVendorPayment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;

/**
 * @extends AbstractEditModelApiRoute<VendorPayment>
 */
class EditVendorPaymentApiRoute extends AbstractEditModelApiRoute
{
    public function __construct(
        private EditVendorPayment $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
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
                'amount' => new RequestParameter(
                    types: ['float', 'integer'],
                ),
                'currency' => new RequestParameter(
                    types: ['string'],
                ),
            ],
            requiredPermissions: ['vendor_payments.edit'],
            modelClass: VendorPayment::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $payment = $this->retrieveModel($context);

        try {
            $this->operation->edit($payment, $context->requestParameters);

            return $payment;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
