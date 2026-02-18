<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\Theme;

/**
 * @extends AbstractListModelsApiRoute<Theme>
 */
class ListThemesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Theme::class,
            features: ['accounts_receivable'],
        );
    }
}
