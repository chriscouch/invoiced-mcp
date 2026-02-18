<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class RenamePlaidIdFieldUnappliedPayments extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('UnappliedPayments')
            ->renameColumn('plaid_id', 'external_id')
            ->update();
    }
}
