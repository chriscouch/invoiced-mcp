<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<CreditNote>
 */
class DeleteCreditNoteRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['credit_notes.delete'],
            modelClass: CreditNote::class,
            features: ['accounts_receivable'],
        );
    }
}
