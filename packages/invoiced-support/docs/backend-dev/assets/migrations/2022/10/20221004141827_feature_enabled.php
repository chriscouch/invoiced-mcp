<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FeatureEnabled extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Features')
            ->addColumn('enabled', 'boolean')
            ->update();
        $this->execute('UPDATE Features SET enabled=1 WHERE value="1"');
    }
}
