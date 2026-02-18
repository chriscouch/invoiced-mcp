<?php

namespace App\AccountsReceivable\Models;

use App\Core\Orm\Query;

class EstimateLineItem extends LineItem
{
    public static function customizeBlankQuery(Query $query): Query
    {
        return $query->where('estimate_id IS NOT NULL');
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['estimate'] = $this->estimate_id;

        return $result;
    }
}
