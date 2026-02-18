<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DropEventCompany extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Events');
        $table->dropForeignKey('company')
            ->removeIndex('company')
            ->update();
        $table->removeColumn('company')->update();
    }
}
