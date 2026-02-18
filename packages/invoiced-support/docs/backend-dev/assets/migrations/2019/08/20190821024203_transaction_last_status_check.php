<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TransactionLastStatusCheck extends MultitenantModelMigration
{
    public function change()
    {
        // Intentionally going without an index for now because
        // the number of transactions that will be retrieved at
        // a time will be a small dataset and the index is only
        // going to result in slower write times. Will change this
        // in a future migration if needed.
        $this->table('Transactions')
            ->addColumn('last_status_check', 'integer')
            ->update();
    }
}
