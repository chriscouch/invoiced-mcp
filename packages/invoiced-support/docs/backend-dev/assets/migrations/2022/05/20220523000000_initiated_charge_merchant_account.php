<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InitiatedChargeMerchantAccount extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InitiatedCharges')
            ->addColumn('merchant_account_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
