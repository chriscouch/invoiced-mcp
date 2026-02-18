<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DeprecateRevRec extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('INSERT INTO Features (tenant_id,feature,value) SELECT id,"revenue_recognition",1 as `value` FROM Companies WHERE Companies.canceled = 0 AND (Companies.trial_ends IS NULL OR Companies.trial_ends = 0) AND EXISTS (SELECT 1 FROM Exports WHERE Exports.tenant_id=Companies.id AND type="revenue_rec")');
    }
}
