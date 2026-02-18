<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;

class ListCreditNoteLineItemsRoute extends AbstractListLineItemsRoute
{
    public function getParentClass(): string
    {
        return CreditNote::class;
    }

    public function getParentPropertyName(): string
    {
        return 'credit_note_id';
    }
}
