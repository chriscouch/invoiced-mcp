<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Contact extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Contacts');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('name', 'string')
            ->addColumn('email', 'string', ['null' => true, 'default' => null])
            ->addColumn('primary', 'boolean')
            ->addColumn('address1', 'string', ['null' => true, 'default' => null, 'length' => 1000])
            ->addColumn('address2', 'string', ['null' => true, 'default' => null])
            ->addColumn('city', 'string', ['null' => true, 'default' => null])
            ->addColumn('state', 'string', ['null' => true, 'default' => null])
            ->addColumn('postal_code', 'string', ['null' => true, 'default' => null])
            ->addColumn('country', 'string', ['length' => 2, 'null' => true, 'default' => null])
            ->addColumn('phone', 'string', ['null' => true, 'default' => null])
            ->addColumn('sms_enabled', 'boolean')
            ->addColumn('department', 'string', ['null' => true, 'default' => null])
            ->addColumn('title', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('customer_id', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addIndex('sms_enabled')
            ->create();
    }
}
