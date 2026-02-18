<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddReceiptEmailToCardAndBankAccount extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Cards')
            ->addColumn('receipt_email', 'string', ['null' => true, 'length' => 1000])
            ->update();

        $this->table('BankAccounts')
            ->addColumn('receipt_email', 'string', ['null' => true, 'length' => 1000])
            ->update();
    }
}
