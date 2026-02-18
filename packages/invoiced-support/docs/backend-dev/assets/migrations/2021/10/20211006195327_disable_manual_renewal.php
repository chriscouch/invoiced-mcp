<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DisableManualRenewal extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute("INSERT INTO Features (tenant_id,feature,value) SELECT id,'subscription_manual_renewal',1 as `value` FROM Companies WHERE Companies.canceled = 0 AND (Companies.trial_ends IS NULL OR Companies.trial_ends = 0) AND EXISTS (SELECT 1 FROM Subscriptions WHERE Subscriptions.tenant_id=Companies.id AND Subscriptions.contract_renewal_mode='manual' AND canceled=0 AND finished=0)");
    }
}
