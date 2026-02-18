<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenPaymentResult extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('AdyenPaymentResults')
            ->addColumn('reference', 'string')
            ->addColumn('result', 'text')
            ->addTimestamps()
            ->addIndex('reference')
            ->create();
    }
}
