<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DefaultConsolidatedSetting extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->addColumn('default_consolidated_invoicing', 'boolean')
            ->update();
    }
}
