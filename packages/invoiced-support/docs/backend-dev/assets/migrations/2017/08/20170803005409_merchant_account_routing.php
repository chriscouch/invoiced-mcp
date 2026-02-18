<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountRouting extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('MerchantAccountRoutings');
        $table->addColumn('method', 'string', ['length' => 32])
            ->addColumn('invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('merchant_account_id', 'integer')
            ->addTimestamps()
            ->addForeignKey('invoice_id', 'Invoices', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE'])
            ->addForeignKey('merchant_account_id', 'MerchantAccounts', 'id', ['update' => 'CASCADE', 'delete' => 'CASCADE']);
        $this->addTenant($table);
        $table->create();
    }
}
