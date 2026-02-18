<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPaymentBatchTotal extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CustomerPaymentBatches')
            ->addColumn('currency', 'string')
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
    }
}
