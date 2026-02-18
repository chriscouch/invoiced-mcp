<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\Theme;

/**
 * @extends AbstractDeleteModelApiRoute<Theme>
 */
class DeleteThemeRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: Theme::class,
            features: ['accounts_receivable'],
        );
    }
}
