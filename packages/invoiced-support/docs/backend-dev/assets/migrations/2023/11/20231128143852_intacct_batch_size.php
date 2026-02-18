<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctBatchSize extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_batch_size', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
