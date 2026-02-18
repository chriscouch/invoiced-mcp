<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class QuickBooksOauth2 extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('QuickBooksAccounts')
            ->changeColumn('access_token_enc', 'text')
            ->addColumn('refresh_token_enc', 'text')
            ->addColumn('refresh_token_expires', 'integer')
            ->addColumn('oauth2', 'boolean', ['default' => false])
            ->update();
    }
}
