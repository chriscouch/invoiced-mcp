<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveFeatureValue extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Features')
            ->removeColumn('value')
            ->update();
    }
}
