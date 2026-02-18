<?php

namespace App\AccountsReceivable\Api;

class RetrieveInvoiceLineItemRoute extends AbstractRetrieveLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'invoice_id';
    }
}
