<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Calendar extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Calendar', ['id' => false, 'primary_key' => ['date']])
            ->addColumn('date', 'date')
            ->create();
    }
}
