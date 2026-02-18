<?php

namespace App\AccountsReceivable\Api;

class RetrieveCreditNoteLineItemRoute extends AbstractRetrieveLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'credit_note_id';
    }
}
