<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ClearPaymentMethod extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('UPDATE PaymentMethods SET merchant_account_id=NULL,gateway=NULL WHERE enabled=0 AND (merchant_account_id IS NOT NULL OR gateway IS NOT NULL) AND id NOT IN ("bitcoin","paypal")');
    }
}
