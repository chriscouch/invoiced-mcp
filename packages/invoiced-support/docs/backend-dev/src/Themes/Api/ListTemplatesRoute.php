<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\Template;

/**
 * @extends AbstractListModelsApiRoute<Template>
 */
class ListTemplatesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Template::class,
            features: ['accounts_receivable'],
        );
    }
}
