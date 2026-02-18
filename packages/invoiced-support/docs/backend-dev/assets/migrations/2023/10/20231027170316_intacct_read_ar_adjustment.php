<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctReadArAdjustment extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_ar_adjustments', 'boolean')
            ->addColumn('ar_adjustment_read_query_addon', 'string', ['default' => null, 'null' => true])
            ->update();
        $this->execute('UPDATE IntacctSyncProfiles SET read_ar_adjustments=read_credit_notes');
    }
}
