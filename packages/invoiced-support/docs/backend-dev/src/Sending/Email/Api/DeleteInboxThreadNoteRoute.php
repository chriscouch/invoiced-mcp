<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\EmailThreadNote;

/**
 * @extends AbstractDeleteModelApiRoute<EmailThreadNote>
 */
class DeleteInboxThreadNoteRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['notes.delete'],
            modelClass: EmailThreadNote::class,
        );
    }
}
