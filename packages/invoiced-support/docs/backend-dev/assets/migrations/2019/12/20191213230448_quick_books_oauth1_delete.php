<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksOauth1Delete extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksAccounts')
            ->removeColumn('access_token_secret_enc')
            ->removeColumn('oauth2')
            ->update();
    }
}
