<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BillBatchPaymentBillsExtension extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BatchBillPayments')
            ->addColumn('error', 'text', ['default' => ''])
            ->update();

        $this->table('BatchBillPaymentBills')
            ->addColumn('bill_number', 'string')
            ->addColumn('vendor_name', 'string')
            ->addColumn('balance', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
    }
}
