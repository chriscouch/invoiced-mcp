<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroRemoveOauth1 extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroAccounts')
            ->removeColumn('oauth2')
            ->removeColumn('access_token_secret_enc')
            ->removeColumn('session_expires')
            ->removeColumn('oauth_id')
            ->update();
    }
}
