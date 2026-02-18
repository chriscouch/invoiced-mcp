<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SlackV2 extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('SlackAccounts')
            ->changeColumn('webhook_url', 'string', ['null' => true, 'default' => null])
            ->changeColumn('webhook_config_url', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
