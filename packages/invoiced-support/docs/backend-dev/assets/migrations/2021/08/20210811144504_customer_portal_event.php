<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPortalEvent extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CustomerPortalEvents');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'integer')
            ->addColumn('timestamp', 'datetime')
            ->addColumn('event', 'integer', ['limit' => Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'signed' => false])
            ->addForeignKey('customer_id', 'Customers', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();
    }
}
