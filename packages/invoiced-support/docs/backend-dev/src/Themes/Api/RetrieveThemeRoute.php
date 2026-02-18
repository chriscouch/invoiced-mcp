<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\Theme;

/**
 * @extends AbstractRetrieveModelApiRoute<Theme>
 */
class RetrieveThemeRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: Theme::class,
            features: ['accounts_receivable'],
        );
    }
}
