<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class LoginDevices extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('LoginDevices')
            ->addColumn('identifier', 'string')
            ->addColumn('user_id', 'integer')
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->create();
    }
}
