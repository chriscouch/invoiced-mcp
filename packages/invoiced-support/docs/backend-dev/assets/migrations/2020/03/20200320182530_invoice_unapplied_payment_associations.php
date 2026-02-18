<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceUnappliedPaymentAssociations extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('InvoiceUnappliedPaymentAssociations', ['id' => false, 'primary_key' => ['invoice_id', 'payment_id']]);
        $table->addColumn('invoice_id', 'integer')
            ->addColumn('payment_id', 'integer')
            ->addColumn('successful', 'boolean', ['null' => true, 'default' => null])
            ->addColumn('certainty', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['invoice_id', 'payment_id'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('payment_id', 'UnappliedPayments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
