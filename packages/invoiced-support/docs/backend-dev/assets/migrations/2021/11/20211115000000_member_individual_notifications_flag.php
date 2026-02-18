<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MemberIndividualNotificationsFlag extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Members');
        if ($table->hasColumn('allow_notification_selections')) {
            $table->removeColumn('allow_notification_selections')
                ->update();
        }
        $this->table('Roles')
            ->addColumn('notifications_edit', 'boolean', ['default' => true])
            ->update();
    }
}
