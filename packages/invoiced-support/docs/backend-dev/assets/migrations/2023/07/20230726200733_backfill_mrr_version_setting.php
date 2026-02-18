<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillMrrVersionSetting extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE SubscriptionBillingSettings SET mrr_version_id=(SELECT id FROM MrrVersions WHERE tenant_id=SubscriptionBillingSettings.tenant_id LIMIT 1) WHERE mrr_version_id IS NULL');
    }
}
