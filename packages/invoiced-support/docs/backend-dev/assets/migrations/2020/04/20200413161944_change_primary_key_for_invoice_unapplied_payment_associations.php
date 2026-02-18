<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChangePrimaryKeyForInvoiceUnappliedPaymentAssociations extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InvoiceUnappliedPaymentAssociations')
            ->changePrimaryKey(['invoice_id', 'payment_id', 'group_id'])
            ->update();
    }
}
