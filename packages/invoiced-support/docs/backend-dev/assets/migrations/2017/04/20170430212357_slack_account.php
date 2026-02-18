<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SlackAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('SlackAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('team_id', 'string')
            ->addColumn('access_token_enc', 'string', ['length' => 678])
            ->addColumn('webhook_url', 'string')
            ->addColumn('webhook_config_url', 'string')
            ->addColumn('webhook_channel', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
