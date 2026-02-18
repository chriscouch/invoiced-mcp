<?php

namespace App\AccountsReceivable\ListQueryBuilders;

use App\AccountsReceivable\Models\CreditNote;

/**
 * @extends DocumentListQueryBuilder<CreditNote>
 */
class CreditNoteListQueryBuilder extends DocumentListQueryBuilder
{
    public static function getClassString(): string
    {
        return CreditNote::class;
    }
}
