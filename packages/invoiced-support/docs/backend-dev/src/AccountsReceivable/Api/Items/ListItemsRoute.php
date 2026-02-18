<?php

namespace App\AccountsReceivable\Api\Items;

use App\AccountsReceivable\Models\Item;
use App\Core\Orm\Query;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Metadata\Api\ListModelsWithMetadataRoute;

class ListItemsRoute extends ListModelsWithMetadataRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Item::class,
            filterableProperties: ['type', 'archived'],
            features: ['accounts_receivable'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $showArchived = (bool) $context->request->query->get('archived');
        if ($showArchived) {
            $query->where('archived', true);
        } else {
            $query->where('archived', false);
        }

        return $query;
    }
}
