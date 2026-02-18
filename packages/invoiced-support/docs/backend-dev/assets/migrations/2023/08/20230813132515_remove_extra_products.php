<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveExtraProducts extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('DELETE FROM InstalledProducts WHERE id IN (SELECT i.id FROM InstalledProducts i JOIN Products p ON p.id=i.product_id WHERE p.name="Accounts Receivable" AND EXISTS (SELECT 1 FROM InstalledProducts i2 JOIN Products p2 ON p2.id=i2.product_id AND i2.tenant_id=i.tenant_id AND p2.name IN ("Advanced Accounts Receivable", "Subscription Billing")))');
    }
}
