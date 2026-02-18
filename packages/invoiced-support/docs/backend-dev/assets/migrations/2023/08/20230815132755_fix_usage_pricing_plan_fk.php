<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixUsagePricingPlanFk extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('UsagePricingPlans')
            ->dropForeignKey('billing_profile_id')
            ->update();

        $this->table('UsagePricingPlans')
            ->addForeignKey('billing_profile_id', 'BillingProfiles', 'id')
            ->update();
    }
}
