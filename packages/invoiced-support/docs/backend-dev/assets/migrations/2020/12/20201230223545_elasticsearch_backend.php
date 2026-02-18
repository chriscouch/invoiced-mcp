<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ElasticsearchBackend extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->changeColumn('search_backend', 'enum', ['default' => null, 'null' => true, 'values' => ['algolia', 'database', 'elasticsearch']])
            ->update();
    }
}
