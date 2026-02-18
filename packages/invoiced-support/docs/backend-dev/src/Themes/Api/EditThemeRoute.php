<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\Theme;

/**
 * @extends AbstractEditModelApiRoute<Theme>
 */
class EditThemeRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: Theme::class,
            features: ['accounts_receivable'],
        );
    }
}
