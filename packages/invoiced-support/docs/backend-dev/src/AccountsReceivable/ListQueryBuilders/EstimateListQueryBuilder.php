<?php

namespace App\AccountsReceivable\ListQueryBuilders;

use App\AccountsReceivable\Models\Estimate;

/**
 * @extends DocumentListQueryBuilder<Estimate>
 */
class EstimateListQueryBuilder extends DocumentListQueryBuilder
{
    public static function getClassString(): string
    {
        return Estimate::class;
    }
}
