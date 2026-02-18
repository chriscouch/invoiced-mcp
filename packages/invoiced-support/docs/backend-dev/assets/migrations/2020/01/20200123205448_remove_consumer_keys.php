<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveConsumerKeys extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NetSuiteAccounts')
            ->removeColumn('consumer_key_enc')
            ->removeColumn('consumer_secret_enc')
            ->update();
    }
}
