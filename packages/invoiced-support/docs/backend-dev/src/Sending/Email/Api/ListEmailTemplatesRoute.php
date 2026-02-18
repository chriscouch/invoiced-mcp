<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\EmailTemplate;

/**
 * @extends AbstractListModelsApiRoute<EmailTemplate>
 */
class ListEmailTemplatesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: EmailTemplate::class,
            filterableProperties: ['type'],
        );
    }
}
