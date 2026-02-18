<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSyncProfilePaymentPlanConfigurations extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('payment_plan_import_settings', 'string', ['default' => null, 'null' => true])
            ->update();
    }
}
