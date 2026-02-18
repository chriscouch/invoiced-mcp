<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingPaymentRename extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('AccountingPaymentMappings')
            ->rename('AccountingTransactionMappings')
            ->update();
    }
}
