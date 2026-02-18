<?php

namespace App\AccountsReceivable\Api;

class DeleteInvoiceLineItemRoute extends AbstractDeleteLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'invoice_id';
    }
}
