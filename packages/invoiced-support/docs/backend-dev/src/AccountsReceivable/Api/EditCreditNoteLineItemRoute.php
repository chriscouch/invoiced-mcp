<?php

namespace App\AccountsReceivable\Api;

class EditCreditNoteLineItemRoute extends AbstractEditLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'credit_note_id';
    }
}
