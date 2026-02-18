<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentBankAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->addColumn('vendor_bank_account_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('vendor_bank_account_id', 'VendorBankAccounts', 'id')
            ->update();

        $this->table('VendorBankAccounts')
            ->changeColumn('check_number', 'integer', ['null' => true, 'default' => null])
            ->changeColumn('layout', 'tinyinteger', ['null' => true, 'default' => null])
            ->update();

        $this->table('VendorBankAccounts')
            ->renameColumn('layout', 'check_layout')
            ->update();
    }
}
