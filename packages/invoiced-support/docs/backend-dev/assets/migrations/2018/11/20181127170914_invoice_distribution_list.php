<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceDistributionList extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('InvoiceDistributions');
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer')
            ->addColumn('enabled', 'boolean')
            ->addColumn('template', 'string', ['null' => true, 'default' => null])
            ->addColumn('department', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('enabled')
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
