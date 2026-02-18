<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPaymentFields extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorPayments')
            ->addColumn('number', 'string', ['length' => 32])
            ->update();

        $this->table('BatchBillPayments')
            ->addColumn('name', 'string')
            ->addColumn('number', 'string', ['length' => 32])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();

        $this->table('BatchBillPayments')
            ->rename('VendorPaymentBatches')
            ->update();
        $this->table('BatchBillPaymentBills')
            ->rename('VendorPaymentBatchBills')
            ->update();
        $this->table('BatchBillPaymentChecks')
            ->rename('VendorPaymentBatchChecks')
            ->update();
    }
}
