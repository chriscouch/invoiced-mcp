<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\LineItem;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Metadata\Api\ListModelsWithMetadataRoute;

abstract class AbstractListLineItemsRoute extends ListModelsWithMetadataRoute
{
    private int $parentId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: LineItem::class,
            filterableProperties: [$this->getParentPropertyName()],
            features: ['accounts_receivable'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $k = $this->getParentPropertyName();
        $this->filter[$k] = $this->parentId;

        $query = parent::buildQuery($context);
        $query->sort('order ASC');

        return $query;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->parentId = (int) $context->request->attributes->get('parent_id');

        // Check that the parent exists first
        $parentClass = $this->getParentClass();
        $parent = $parentClass::find($this->parentId);
        if (!$parent) {
            throw new InvalidRequest($this->humanClassName($parentClass).' was not found: '.$this->parentId, 404);
        }

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
