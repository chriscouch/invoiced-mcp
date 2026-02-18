<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingStatisticsInvoiceCadence extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ChasingStatistics')
            ->addColumn('invoice_cadence_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
