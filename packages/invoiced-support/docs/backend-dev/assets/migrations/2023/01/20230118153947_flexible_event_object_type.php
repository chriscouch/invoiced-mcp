<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlexibleEventObjectType extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Events')
            ->addColumn('object_type_id', 'integer')
            ->addColumn('type_id', 'integer')
            ->update();
        $this->table('EventAssociations')
            ->addColumn('object_type', 'integer')
            ->update();
        $this->ensureInstantEnd();
    }
}
