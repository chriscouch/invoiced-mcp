<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Note;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<Note>
 */
class EditNoteRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'customer_id' => new RequestParameter(),
                'invoice_id' => new RequestParameter(),
                'user_id' => new RequestParameter(),
                'notes' => new RequestParameter(),
            ],
            requiredPermissions: ['notes.edit'],
            modelClass: Note::class,
            features: ['accounts_receivable'],
        );
    }
}
