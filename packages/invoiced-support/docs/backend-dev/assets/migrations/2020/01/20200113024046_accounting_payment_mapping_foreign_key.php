<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountingPaymentMappingForeignKey extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('AccountingPaymentMappings')
            ->addForeignKey('transaction_id', 'Transactions', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
