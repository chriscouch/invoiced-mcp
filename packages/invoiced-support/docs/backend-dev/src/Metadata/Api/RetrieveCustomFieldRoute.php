<?php

namespace App\Metadata\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Metadata\Models\CustomField;

/**
 * @extends AbstractRetrieveModelApiRoute<CustomField>
 */
class RetrieveCustomFieldRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: CustomField::class,
        );
    }
}
