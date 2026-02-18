<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroAccessTokenUpdate extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroAccounts')
            ->changeColumn('access_token_enc', 'text')
            ->update();
    }
}
