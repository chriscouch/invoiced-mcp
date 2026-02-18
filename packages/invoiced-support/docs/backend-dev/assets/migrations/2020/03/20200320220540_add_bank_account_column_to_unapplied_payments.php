<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddBankAccountColumnToUnappliedPayments extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('UnappliedPayments')
            ->addColumn('plaid_bank_account_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('plaid_bank_account_id', 'PlaidBankAccountLinks', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->addForeignKey('customer', 'Customers', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();
    }
}
