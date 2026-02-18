<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class VoidCreditNoteRoute extends VoidDocumentRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['credit_notes.void'],
            modelClass: CreditNote::class,
            features: ['accounts_receivable'],
        );
    }
}
