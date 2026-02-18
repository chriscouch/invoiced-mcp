<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingStatisticsInvoiceId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ChasingStatistics')->addColumn('invoice_id', 'integer')
            ->addIndex(['invoice_id'])
            ->update();
    }
}
