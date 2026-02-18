<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('MerchantAccounts');
        $this->addTenant($table);
        $table->addColumn('gateway', 'string')
            ->addColumn('gateway_id', 'string', ['collation' => 'utf8_bin'])
            ->addColumn('name', 'string', ['null' => true, 'default' => null])
            ->addColumn('credentials_enc', 'text')
            ->addTimestamps()
            ->addIndex('gateway')
            ->addIndex('gateway_id')
            ->create();
    }
}
