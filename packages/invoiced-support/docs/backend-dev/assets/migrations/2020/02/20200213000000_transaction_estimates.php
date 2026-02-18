<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TransactionEstimates extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Transactions')
            ->addColumn('estimate_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('estimate_id', 'Estimates', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
