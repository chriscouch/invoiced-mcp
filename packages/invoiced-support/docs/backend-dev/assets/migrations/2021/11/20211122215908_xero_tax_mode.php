<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroTaxMode extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('tax_mode', 'string', ['length' => 15])
            ->removeColumn('auto_import')
            ->removeColumn('next_import')
            ->removeColumn('tax_inclusive')
            ->removeColumn('tax_code')
            ->update();
        $this->execute('UPDATE XeroSyncProfiles SET tax_mode="tax_line_item"');
    }
}
