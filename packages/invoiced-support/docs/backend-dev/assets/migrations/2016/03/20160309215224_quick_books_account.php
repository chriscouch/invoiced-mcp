<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('QuickBooksAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('realm_id', 'string')
            ->addColumn('name', 'string', ['null' => true, 'default' => null])
            ->addColumn('access_token_enc', 'string', ['length' => 678])
            ->addColumn('access_token_secret_enc', 'string', ['length' => 678])
            ->addColumn('expires', 'integer')
            ->addTimestamps()
            ->create();
    }
}
