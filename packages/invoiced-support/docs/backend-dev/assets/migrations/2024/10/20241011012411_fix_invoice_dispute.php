<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixInvoiceDispute extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('DisputeReasons')
            ->removeColumn('currency')
            ->removeColumn('amount')
            ->update();

        $this->table('InvoiceDisputes')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('accepted_amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
    }
}
