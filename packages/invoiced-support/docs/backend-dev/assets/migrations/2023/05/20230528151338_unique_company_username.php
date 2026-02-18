<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueCompanyUsername extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Companies')
            ->addIndex('username', ['unique' => true])
            ->update();
        $this->table('Companies')
            ->removeIndexByName('username')
            ->update();
    }
}
