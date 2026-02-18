<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddIsRemittanceAdviceFieldToInvoiceUnappliedPaymentAssociationsTable extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InvoiceUnappliedPaymentAssociations')
            ->addColumn('is_remittance_advice', 'boolean', ['default' => false])
            ->update();
    }
}
