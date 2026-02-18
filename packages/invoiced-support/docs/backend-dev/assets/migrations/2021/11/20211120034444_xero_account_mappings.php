<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class XeroAccountMappings extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('XeroSyncProfiles')
            ->addColumn('discount_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('sales_tax_account', 'string', ['null' => true, 'default' => null])
            ->addColumn('convenience_fee_account', 'string', ['null' => true, 'default' => null])
            ->update();
        $this->execute('UPDATE XeroSyncProfiles SET discount_account=item_account');
    }
}
