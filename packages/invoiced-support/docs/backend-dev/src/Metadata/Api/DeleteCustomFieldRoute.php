<?php

namespace App\Metadata\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Metadata\Models\CustomField;

/**
 * @extends AbstractDeleteModelApiRoute<CustomField>
 */
class DeleteCustomFieldRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: CustomField::class,
        );
    }
}
