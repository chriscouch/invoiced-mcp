<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingInvoiceMapping extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountingInvoiceMappings', ['id' => false, 'primary_key' => ['invoice_id']]);
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('accounting_id', 'string')
            ->addColumn('source', 'enum', ['values' => ['accounting_system', 'invoiced']])
            ->addIndex(['integration_id', 'accounting_id'])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
