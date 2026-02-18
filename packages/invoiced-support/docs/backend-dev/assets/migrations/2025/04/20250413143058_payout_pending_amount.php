<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PayoutPendingAmount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Payouts')
            ->addColumn('pending_amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('net', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->execute('UPDATE Payouts SET pending_amount=0;');
        $this->execute('UPDATE Payouts SET net=amount;');
    }
}
