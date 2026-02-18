<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;

class ListInvoiceLineItemsRoute extends AbstractListLineItemsRoute
{
    public function getParentClass(): string
    {
        return Invoice::class;
    }

    public function getParentPropertyName(): string
    {
        return 'invoice_id';
    }
}
