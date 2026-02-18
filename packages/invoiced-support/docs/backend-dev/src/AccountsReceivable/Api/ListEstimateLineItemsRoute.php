<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;

class ListEstimateLineItemsRoute extends AbstractListLineItemsRoute
{
    public function getParentClass(): string
    {
        return Estimate::class;
    }

    public function getParentPropertyName(): string
    {
        return 'estimate_id';
    }
}
