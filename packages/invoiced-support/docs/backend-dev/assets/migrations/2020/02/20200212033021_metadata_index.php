<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MetadataIndex extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Metadata')
            ->addIndex(['tenant_id', 'object_type', 'key'])
            ->update();
    }
}
