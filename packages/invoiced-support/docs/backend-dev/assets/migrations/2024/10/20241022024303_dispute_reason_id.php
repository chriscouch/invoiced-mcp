<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DisputeReasonId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('InvoiceDisputes')
            ->renameColumn('reason', 'reason_id')
            ->update();
    }
}
