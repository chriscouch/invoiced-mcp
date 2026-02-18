<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Operations\EditBill;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\ModelException;
use Carbon\CarbonImmutable;

/**
 * @extends AbstractModelApiRoute<Bill>
 */
class EditBillRoute extends AbstractModelApiRoute
{
    public function __construct(private readonly EditBill $operation)
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
                'due_date' => new RequestParameter(types: ['string', 'null']),
                'currency' => new RequestParameter(types: ['string']),
                'line_items' => new RequestParameter(types: ['array']),
                'approval_workflow' => new RequestParameter(types: ['int', 'null']),
            ],
            requiredPermissions: ['bills.create'],
            modelClass: Bill::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): Bill
    {
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $model = $this->retrieveModel($context);
        $parameters = $this->hydrateRelationships($context->requestParameters);

        if ($parameters['date']) {
            $parameters['date'] = new CarbonImmutable($parameters['date']);
        }

        if ($parameters['due_date']) {
            $parameters['due_date'] = new CarbonImmutable($parameters['due_date']);
        }

        try {
            $this->operation->edit($model, $parameters);

            return $model;
        } catch (ModelException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
