<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ArModule extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO Features (tenant_id, feature, enabled) SELECT id, "module_accounts_receivable", 1 FROM Companies');
    }
}
