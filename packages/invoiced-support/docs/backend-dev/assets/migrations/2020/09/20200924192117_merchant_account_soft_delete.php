<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MerchantAccountSoftDelete extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('MerchantAccounts')
            ->addColumn('deleted', 'boolean')
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
