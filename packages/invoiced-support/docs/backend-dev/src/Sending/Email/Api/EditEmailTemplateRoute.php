<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\EmailTemplate;

/**
 * @extends AbstractEditModelApiRoute<EmailTemplate>
 */
class EditEmailTemplateRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: EmailTemplate::class,
        );
    }
}
