<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillBatchPaymentRelations extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->addColumn('batch_bill_payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('batch_bill_payment_id', 'BatchBillPayments')
            ->update();

        $this->table('BatchBillPaymentBills')
            ->addColumn('vendor_payment_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('vendor_payment_id', 'VendorPayments', 'id')
            ->update();
    }
}
