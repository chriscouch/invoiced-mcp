<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AlterROlesAddWorkatoEmbeddedEnabledColumn extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Roles')
            ->addColumn('workato_embedded_enabled', 'boolean', ['default' => false])
            ->update();
    }
}