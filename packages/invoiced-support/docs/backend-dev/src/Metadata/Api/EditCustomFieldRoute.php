<?php

namespace App\Metadata\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Metadata\Models\CustomField;

/**
 * @extends AbstractEditModelApiRoute<CustomField>
 */
class EditCustomFieldRoute extends AbstractEditModelApiRoute
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
