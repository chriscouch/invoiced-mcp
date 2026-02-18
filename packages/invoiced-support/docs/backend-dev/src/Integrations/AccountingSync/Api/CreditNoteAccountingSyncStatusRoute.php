<?php

namespace App\Integrations\AccountingSync\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;

class CreditNoteAccountingSyncStatusRoute extends AbstractAccountingSyncStatusRoute
{
    const MAPPING_CLASS = AccountingCreditNoteMapping::class;
    const MAPPING_ID = 'credit_note_id';

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CreditNote::class,
        );
    }
}
