<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('XeroAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('oauth_id', 'string')
            ->addColumn('organization_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('name', 'string', ['null' => true, 'default' => null])
            ->addColumn('access_token_enc', 'string', ['length' => 678])
            ->addColumn('access_token_secret_enc', 'string', ['length' => 678])
            ->addColumn('session_handle_enc', 'string', ['length' => 678])
            ->addColumn('expires', 'integer')
            ->addColumn('session_expires', 'integer')
            ->addTimestamps()
            ->addIndex('organization_id')
            ->create();
    }
}
