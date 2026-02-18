<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueQuotas extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Quotas')
            ->addIndex(['tenant_id', 'quota_type'], ['unique' => true])
            ->update();

        $this->table('UsagePricingPlans')
            ->addIndex(['tenant_id', 'usage_type'], ['unique' => true])
            ->update();
    }
}
