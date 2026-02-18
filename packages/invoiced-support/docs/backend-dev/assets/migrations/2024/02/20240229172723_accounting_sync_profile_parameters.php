<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingSyncProfileParameters extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AccountingSyncProfiles')
            ->addColumn('parameters', 'json', ['default' => '{}', 'null' => false])
            ->update();
    }
}
