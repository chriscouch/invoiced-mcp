<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailThreadRelatedDocumentIndexingChange extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('EmailThreads')
            ->removeIndex(['tenant_id', 'inbox_id', 'related_to_type', 'related_to_id'])
            ->addIndex(['tenant_id', 'related_to_type', 'related_to_id'])
            ->update();
    }
}
