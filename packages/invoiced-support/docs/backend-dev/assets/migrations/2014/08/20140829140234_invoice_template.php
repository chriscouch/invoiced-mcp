<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceTemplate extends MultitenantModelMigration
{
    public function change()
    {
        // This is done in order to facilitate the change in migration order.
        // New migrations do not need this type of check.
        if ($this->hasTable('InvoiceTemplates')) {
            return;
        }

        $table = $this->table('InvoiceTemplates');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->addColumn('chase', 'boolean')
            ->addColumn('payment_terms', 'string', ['length' => 20, 'null' => true, 'default' => null])
            ->addColumn('items', 'text')
            ->addColumn('discounts', 'string')
            ->addColumn('taxes', 'string')
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
