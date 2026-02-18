<?php

namespace App\AccountsReceivable\Api\Items;

use App\AccountsReceivable\Models\Item;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<Item>
 */
class CreateItemRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'id' => new RequestParameter(),
                'name' => new RequestParameter(),
                'type' => new RequestParameter(),
                'currency' => new RequestParameter(),
                'unit_cost' => new RequestParameter(),
                'description' => new RequestParameter(),
                'gl_account' => new RequestParameter(),
                'discountable' => new RequestParameter(),
                'taxable' => new RequestParameter(),
                'taxes' => new RequestParameter(),
                'avalara_tax_code' => new RequestParameter(),
                'avalara_location_code' => new RequestParameter(),
                'metadata' => new RequestParameter(),
            ],
            requiredPermissions: ['catalog.edit'],
            modelClass: Item::class,
            features: ['accounts_receivable'],
        );
    }
}
