<?php

namespace App\AccountsReceivable\Api;

class EditEstimateLineItemRoute extends AbstractEditLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'estimate_id';
    }
}
