<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;

class CreateEstimateLineItemRoute extends AbstractCreateLineItemRoute
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
