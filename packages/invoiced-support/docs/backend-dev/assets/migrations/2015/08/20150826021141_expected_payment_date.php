<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ExpectedPaymentDate extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ExpectedPaymentDates', ['id' => false, 'primary_key' => ['invoice_id']]);
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('date', 'integer', ['default' => null, 'null' => true])
            ->addColumn('method', 'string', ['length' => 32])
            ->addColumn('notes', 'string')
            ->addTimestamps()
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
