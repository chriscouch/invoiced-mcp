<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentBatchItemRename extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPaymentBatches')
            ->renameColumn('layout', 'check_layout')
            ->update();

        $this->table('VendorPaymentBatches')
            ->changeColumn('check_layout', 'tinyinteger', ['null' => true, 'default' => null])
            ->removeColumn('error')
            ->update();

        $this->table('VendorPayments')
            ->renameColumn('batch_bill_payment_id', 'vendor_payment_batch_id')
            ->update();

        $this->table('VendorPaymentBatchBills')
            ->renameColumn('balance', 'amount')
            ->renameColumn('batch_id', 'vendor_payment_batch_id')
            ->addColumn('error', 'string', ['null' => true, 'default' => null])
            ->update();

        $this->table('VendorPaymentBatchChecks')
            ->renameColumn('batch_id', 'vendor_payment_batch_id')
            ->update();

        $this->table('VendorPayments')
            ->dropForeignKey('vendor_payment_batch_id')
            ->update();
        $this->table('VendorPayments')
            ->addForeignKey('vendor_payment_batch_id', 'VendorPaymentBatches', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();

        $this->table('VendorPaymentBatchBills')
            ->dropForeignKey('vendor_payment_id')
            ->update();
        $this->table('VendorPaymentBatchBills')
            ->addForeignKey('vendor_payment_id', 'VendorPayments', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();

        $this->table('VendorPaymentBatches')
            ->addColumn('initial_check_number', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
