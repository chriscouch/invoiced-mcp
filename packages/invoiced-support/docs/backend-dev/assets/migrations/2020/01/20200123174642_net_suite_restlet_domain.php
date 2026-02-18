<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NetSuiteRestletDomain extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('NetSuiteAccounts')
            ->addColumn('restlet_domain', 'string')
            ->update();
    }
}
