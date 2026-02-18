<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ImportsDisableEvents extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Imports')
            ->addColumn('disable_events', 'smallinteger')
            ->update();
    }
}
