<?php

namespace App\AccountsReceivable\Api;

class DeleteCreditNoteLineItemRoute extends AbstractDeleteLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'credit_note_id';
    }
}
