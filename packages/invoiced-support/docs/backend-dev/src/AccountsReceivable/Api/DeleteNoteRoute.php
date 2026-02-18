<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Note;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<Note>
 */
class DeleteNoteRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['notes.delete'],
            modelClass: Note::class,
            features: ['accounts_receivable'],
        );
    }
}
