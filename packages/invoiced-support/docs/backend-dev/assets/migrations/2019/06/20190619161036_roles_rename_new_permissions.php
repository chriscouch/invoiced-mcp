<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RolesRenameNewPermissions extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Roles');
        $table->renameColumn('subscription_create', 'subscriptions_create')
            ->renameColumn('subscription_edit', 'subscriptions_edit')
            ->renameColumn('subscription_delete', 'subscriptions_delete')
            ->renameColumn('task_create', 'tasks_create')
            ->renameColumn('task_edit', 'tasks_edit')
            ->renameColumn('task_delete', 'tasks_delete')
            ->update();
    }
}
