<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveEventObject extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $table = $this->table('Events');
        if ($table->hasColumn('object')) {
            $table->removeColumn('object')
                ->removeColumn('previous')
                ->update();
        }
        $this->ensureInstantEnd();
    }
}
