<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LegacyEmailFeatureFlag extends MultitenantModelMigration
{
    public function change()
    {
        // Explicitly disabled A/R Inbox
        $this->execute('INSERT INTO Features (tenant_id,feature,value) SELECT id,"legacy_emails",1 as `value` FROM Companies where exists (select 1 from Features where tenant_id=Companies.id and feature="inboxes" and value="0")');
        // On a plan without A/R Inbox and not enabled yet
        $this->execute('INSERT INTO Features (tenant_id,feature,value) SELECT id,"legacy_emails",1 as `value` FROM Companies where plan not in ("trial", "test-mode") and not exists (select 1 from Features where tenant_id=Companies.id and feature="module_collections" and value="1") and not exists (select 1 from Features where tenant_id=Companies.id and feature="inboxes" and value="1")');
    }
}
