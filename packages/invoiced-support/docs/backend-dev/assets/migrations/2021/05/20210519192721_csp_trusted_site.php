<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CspTrustedSite extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('CspTrustedSites');
        $this->addTenant($table);
        $table->addColumn('url', 'string')
            ->addColumn('connect', 'boolean')
            ->addColumn('font', 'boolean')
            ->addColumn('frame', 'boolean')
            ->addColumn('img', 'boolean')
            ->addColumn('media', 'boolean')
            ->addColumn('object', 'boolean')
            ->addColumn('script', 'boolean')
            ->addColumn('style', 'boolean')
            ->addTimestamps()
            ->create();
    }
}
