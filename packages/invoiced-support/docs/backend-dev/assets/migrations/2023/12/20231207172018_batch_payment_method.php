<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BatchPaymentMethod extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BatchBillPayments')
            ->addColumn('payment_method', 'string')
            ->update();
        $this->execute('UPDATE BatchBillPayments SET payment_method="print_check"');
    }
}
