<?php

namespace App\AccountsReceivable\Api;

class EditInvoiceLineItemRoute extends AbstractEditLineItemRoute
{
    public function getParentPropertyName(): string
    {
        return 'invoice_id';
    }
}
