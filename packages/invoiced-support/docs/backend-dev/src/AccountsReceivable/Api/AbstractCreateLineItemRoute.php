<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\LineItem;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

abstract class AbstractCreateLineItemRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: [],
            modelClass: LineItem::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $k = $this->getParentPropertyName();
        $requestParameters = $context->requestParameters;
        $requestParameters[$k] = (int) $context->request->attributes->get('parent_id');
        $context = $context->withRequestParameters($requestParameters);

        // Check that the parent exists first
        $parentClass = $this->getParentClass();
        $parentId = (int) $context->request->attributes->get('parent_id');
        $parent = $parentClass::find($parentId);
        if (!$parent) {
            throw new InvalidRequest($this->humanClassName($parentClass).' was not found: '.$parentId, 404);
        }

        $modelClass = $this->model::class;
        /** @var LineItem $model */
        $model = new $modelClass();
        $model->setParent($parent);

        return parent::buildResponse($context);
    }

    /**
     * Gets the class of the LineItem parent.
     */
    abstract public function getParentClass(): string;

    /**
     * Gets the name of the property on the LineItem
     * model to reference the parent, i.e. customer_id.
     */
    abstract public function getParentPropertyName(): string;
}
