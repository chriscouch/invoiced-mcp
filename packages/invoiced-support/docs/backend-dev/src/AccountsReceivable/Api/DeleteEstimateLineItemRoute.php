<?php

namespace App\AccountsReceivable\Api;

class DeleteEstimateLineItemRoute extends AbstractDeleteLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'estimate_id';
    }
}
