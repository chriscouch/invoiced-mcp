<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroOauth2 extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroAccounts')
            ->addColumn('oauth2', 'boolean', ['default' => false])
            ->update();
    }
}
