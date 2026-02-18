<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TwilioAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('TwilioAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('account_sid', 'string')
            ->addColumn('from_number', 'string')
            ->addColumn('auth_token_enc', 'string', ['length' => 678])
            ->addTimestamps()
            ->create();
    }
}
