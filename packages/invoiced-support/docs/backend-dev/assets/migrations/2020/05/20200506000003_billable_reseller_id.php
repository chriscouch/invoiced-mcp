<?php

use Phinx\Migration\AbstractMigration;

final class BillableResellerId extends AbstractMigration
{
    public function change()
    {
        $this->table('Companies')
            ->addColumn('reseller_id', 'integer', ['null' => true, 'default' => null])
            ->changeColumn('billing_system', 'enum', ['default' => null, 'null' => true, 'values' => ['invoiced', 'stripe', 'reseller']])
            ->addIndex('reseller_id')
            ->update();
    }
}
