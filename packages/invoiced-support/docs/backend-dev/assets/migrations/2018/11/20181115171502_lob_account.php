<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LobAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('LobAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('key_enc', 'string', ['length' => 678])
            ->addColumn('return_envelopes', 'boolean')
            ->addColumn('use_color', 'boolean')
            ->addTimestamps()
            ->create();
    }
}
