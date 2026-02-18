<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillBatchPaymentUpdates extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('VendorBankAccounts');
        $this->addTenant($table);
        $table->addTimestamps()
            ->addColumn('name', 'string')
            ->addColumn('check_number', 'integer')
            ->addColumn('layout', 'tinyinteger')
            ->create();

        $this->table('BatchBillPayments')
            ->addColumn('layout', 'integer')
            ->addColumn('vendor_bank_account_id', 'integer')
            ->addForeignKey('vendor_bank_account_id', 'VendorBankAccounts', 'id')
            ->update();

        $this->table('BatchBillPaymentBills')
            ->removeIndex(['check_number', 'tenant_id'])
            ->removeColumn('check_number')
            ->removeColumn('vendor_name')
            ->addColumn('vendor_id', 'integer')
            ->update();

        $table = $this->table('BatchBillPaymentChecks');
        $this->addTenant($table);
        $table->addColumn('data', 'json')
            ->addColumn('check_number', 'integer')
            ->addColumn('batch_id', 'integer')
            ->addForeignKey('batch_id', 'BatchBillPayments', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();
    }
}
