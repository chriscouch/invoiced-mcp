<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingConvenienceFeeMapping extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountingConvenienceFeeMappings', ['id' => false, 'primary_key' => ['payment_id']]);
        $this->addTenant($table);
        $table->addColumn('payment_id', 'integer')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('accounting_id', 'string')
            ->addColumn('source', 'enum', ['values' => ['accounting_system', 'invoiced']])
            ->addIndex(['integration_id', 'accounting_id'])
            ->addTimestamps()
            ->addForeignKey('payment_id', 'Payments', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
