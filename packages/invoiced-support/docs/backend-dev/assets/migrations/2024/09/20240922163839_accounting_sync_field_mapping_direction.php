<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncFieldMappingDirection extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AccountingSyncFieldMappings')
            ->addColumn('direction', 'tinyinteger', ['default' => 1])
            ->addColumn('value', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
