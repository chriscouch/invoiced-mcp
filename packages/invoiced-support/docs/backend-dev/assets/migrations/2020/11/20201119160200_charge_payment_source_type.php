<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChargePaymentSourceType extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Charges')
            ->changeColumn('payment_source_type', 'enum', ['values' => ['card', 'bank_account'], 'null' => true, 'default' => null])
            ->update();
    }
}
