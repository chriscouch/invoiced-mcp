<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingPaymentMapping extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AccountingPaymentMappings', ['id' => false, 'primary_key' => ['transaction_id']]);
        $this->addTenant($table);
        $table->addColumn('transaction_id', 'integer')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->addColumn('accounting_id', 'string')
            ->addColumn('source', 'enum', ['values' => ['accounting_system', 'invoiced']])
            ->addIndex(['integration_id', 'accounting_id'])
            ->addTimestamps()
            ->create();
    }
}
