<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerGateway extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Customers')
            ->addColumn('ach_gateway_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('cc_gateway_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('ach_gateway_id', 'MerchantAccounts', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('cc_gateway_id', 'MerchantAccounts', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
