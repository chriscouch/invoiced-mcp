<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\LineItem;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

abstract class AbstractRetrieveLineItemRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: LineItem::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $lineItem = parent::buildResponse($context);

        $k = $this->getParentPropertyName();
        $parentId = (int) $context->request->attributes->get('parent_id');
        if ($this->model->$k != $parentId) {
            throw $this->modelNotFoundError();
        }

        return $lineItem;
    }

    /**
     * Gets the name of the property on the LineItem
     * model to reference the parent, i.e. customer_id.
     */
    abstract public function getParentPropertyName(): string;
}
