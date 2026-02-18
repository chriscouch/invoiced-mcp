<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Sending\Email\Models\EmailThreadNote;

class EditInboxThreadNoteRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'note' => new RequestParameter(required: true),
            ],
            requiredPermissions: ['notes.edit'],
            modelClass: EmailThreadNote::class,
        );
    }
}
