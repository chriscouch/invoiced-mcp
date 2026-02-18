<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ShippingDetails extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ShippingDetails');
        $this->addTenant($table);
        $table->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('estimate_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('name', 'string')
            ->addColumn('attention_to', 'string', ['null' => true, 'default' => null])
            ->addColumn('address1', 'string', ['null' => true, 'default' => null])
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string', ['null' => true, 'default' => null])
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->create();
    }
}
