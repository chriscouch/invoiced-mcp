<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentMethodConvenienceFee extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PaymentMethods')
            ->addColumn('convenience_fee', 'integer')
            ->update();
    }
}
