<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CashApplicationBankAccounts extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CashApplicationBankAccounts');
        $this->addTenant($table);
        $table->addTimestamps()
            ->addColumn('plaid_link_id', 'integer')
            ->addColumn('data_starts_at', 'integer')
            ->addColumn('last_retrieved_data_at', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('plaid_link_id', 'PlaidBankAccountLinks', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
        $this->execute('INSERT INTO CashApplicationBankAccounts (id, tenant_id, plaid_link_id, data_starts_at, last_retrieved_data_at) SELECT id, tenant_id, id, data_starts_at, last_retrieved_data_at FROM PlaidBankAccountLinks');
    }
}
