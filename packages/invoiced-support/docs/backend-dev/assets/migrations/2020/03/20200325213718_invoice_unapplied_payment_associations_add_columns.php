<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceUnappliedPaymentAssociationsAddColumns extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InvoiceUnappliedPaymentAssociations')
            ->addColumn('primary', 'boolean', ['default' => false])
            ->addColumn('short_pay', 'boolean', ['default' => false])
            ->addColumn('group_id', 'string', ['default' => ''])
            ->update();

        $this->table('UnappliedPayments')
            ->addColumn('matched', 'boolean', ['default' => false])
            ->update();
    }
}
