<?php

namespace App\AccountsReceivable\Models;

use App\Core\Orm\Query;

class CreditNoteLineItem extends LineItem
{
    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->where('credit_note_id IS NOT NULL');
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['credit_note'] = $this->credit_note_id;

        return $result;
    }
}
