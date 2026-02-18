<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ArInboxSetting extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AccountsReceivableSettings')
            ->addColumn('inbox_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('inbox_id', 'Inboxes', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
