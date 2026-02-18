<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\LineItem;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

abstract class AbstractEditLineItemRoute extends AbstractEditModelApiRoute
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
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $this->retrieveModel($context);

        $k = $this->getParentPropertyName();
        $parentId = (int) $context->request->attributes->get('parent_id');
        if ($this->model->$k != $parentId) {
            throw $this->modelNotFoundError();
        }

        return parent::buildResponse($context);
    }

    /**
     * Gets the name of the property on the LineItem
     * model to reference the parent, i.e. customer_id.
     */
    abstract public function getParentPropertyName(): string;
}
