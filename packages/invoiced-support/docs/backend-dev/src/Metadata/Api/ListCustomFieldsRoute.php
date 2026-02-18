<?php

namespace App\Metadata\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Metadata\Models\CustomField;

/**
 * @extends AbstractListModelsApiRoute<CustomField>
 */
class ListCustomFieldsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CustomField::class,
            features: [],
        );
    }
}
