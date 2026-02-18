<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RolesNewPermissions extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Roles');
        $table->addColumn('comments_create', 'boolean')
            ->addColumn('notes_create', 'boolean')
            ->addColumn('notes_edit', 'boolean')
            ->addColumn('notes_delete', 'boolean')
            ->addColumn('subscription_create', 'boolean')
            ->addColumn('subscription_edit', 'boolean')
            ->addColumn('subscription_delete', 'boolean')
            ->addColumn('task_create', 'boolean')
            ->addColumn('task_edit', 'boolean')
            ->addColumn('task_delete', 'boolean')
            ->update();
    }
}
