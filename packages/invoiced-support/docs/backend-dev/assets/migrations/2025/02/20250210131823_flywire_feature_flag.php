<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywireFeatureFlag extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO Features (tenant_id, feature, enabled) SELECT tenant_id, "flywire", 1 FROM MerchantAccounts WHERE gateway="flywire"');
    }
}
