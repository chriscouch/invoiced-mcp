<?php

namespace App\AccountsReceivable\Api;

class RetrieveEstimateLineItemRoute extends AbstractRetrieveLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'estimate_id';
    }
}
