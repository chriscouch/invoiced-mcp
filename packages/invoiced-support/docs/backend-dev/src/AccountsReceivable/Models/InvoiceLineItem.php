<?php

namespace App\AccountsReceivable\Models;

use App\Core\Orm\Query;

class InvoiceLineItem extends LineItem
{
    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->where('invoice_id IS NOT NULL');
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['invoice'] = $this->invoice_id;

        return $result;
    }
}
