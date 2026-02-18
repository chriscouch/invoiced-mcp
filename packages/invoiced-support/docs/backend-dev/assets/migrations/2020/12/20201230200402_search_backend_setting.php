<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class SearchBackendSetting extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->addColumn('search_backend', 'enum', ['default' => null, 'null' => true, 'values' => ['algolia', 'database']])
            ->update();
    }
}
