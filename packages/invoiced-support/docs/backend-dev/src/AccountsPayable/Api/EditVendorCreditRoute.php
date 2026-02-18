<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Operations\EditVendorCredit;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;
use Carbon\CarbonImmutable;

/**
 * @extends AbstractModelApiRoute<VendorCredit>
 */
class EditVendorCreditRoute extends AbstractModelApiRoute
{
    public function __construct(private readonly EditVendorCredit $operation)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'vendor' => new RequestParameter(types: ['int']),
                'number' => new RequestParameter(types: ['string']),
                'date' => new RequestParameter(types: ['string']),
                'currency' => new RequestParameter(types: ['string']),
                'line_items' => new RequestParameter(types: ['array']),
                'approval_workflow' => new RequestParameter(types: ['int', 'null']),
            ],
            requiredPermissions: ['bills.edit'],
            modelClass: VendorCredit::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorCredit
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $model = $this->retrieveModel($context);
        $parameters = $this->hydrateRelationships($context->requestParameters);

        if ($parameters['date']) {
            $parameters['date'] = new CarbonImmutable($parameters['date']);
        }

        try {
            $this->operation->edit($model, $parameters);

            return $model;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
