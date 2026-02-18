<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;

class CreateCreditNoteLineItemRoute extends AbstractCreateLineItemRoute
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
