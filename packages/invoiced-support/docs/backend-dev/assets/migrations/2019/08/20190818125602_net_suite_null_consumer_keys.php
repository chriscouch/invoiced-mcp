<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NetSuiteNullConsumerKeys extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NetSuiteAccounts')
            ->changeColumn('consumer_key_enc', 'string', ['length' => 678, 'null' => true])
            ->changeColumn('consumer_secret_enc', 'string', ['length' => 678, 'null' => true])
            ->update();
    }
}
