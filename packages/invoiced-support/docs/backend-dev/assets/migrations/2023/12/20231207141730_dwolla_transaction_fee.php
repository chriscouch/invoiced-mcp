<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaTransactionFee extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DwollaAccounts')
            ->addColumn('pay_fee_when_sender', 'boolean')
            ->addColumn('transaction_fee', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->execute('UPDATE DwollaAccounts SET transaction_fee=1');

        $this->table('DwollaPayments')
            ->addColumn('fee_paid_by_sender', 'boolean')
            ->update();
    }
}
