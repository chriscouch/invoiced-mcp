<?php

namespace App\Metadata\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Metadata\Models\CustomField;

/**
 * @extends AbstractCreateModelApiRoute<CustomField>
 */
class CreateCustomFieldRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CustomField::class,
        );
    }
}
