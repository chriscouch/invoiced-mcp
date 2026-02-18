<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillMrrVersions extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO MrrVersions (tenant_id, currency) SELECT c.id, c.currency FROM Features JOIN Companies c ON c.id=tenant_id WHERE feature="module_subscription_billing" AND enabled=1');
    }
}
