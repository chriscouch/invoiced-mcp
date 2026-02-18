<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MoreCompanyTypes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Companies')
            ->changeColumn('type', 'enum', ['values' => ['company', 'person', 'government', 'non_profit']])
            ->update();
        $this->ensureInstantEnd();
    }
}
