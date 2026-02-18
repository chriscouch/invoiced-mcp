<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixMissingAutomations extends MultitenantModelMigration
{
    public function up(): void
    {
        $result = $this->fetchRow('SELECT id FROM Products where name = "Automations"');

        if (!$id = $result['id']) {
            return;
        }

        $this->execute("INSERT IGNORE INTO Features SELECT null, tenant_id, 'automations', 1 FROM InstalledProducts where product_id = {$id}");

        $this->execute("DELETE FROM InstalledProducts WHERE product_id = {$id}");
    }
}
