<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DoNotBillUsage extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('BilledVolumes')
            ->addColumn('do_not_bill', 'boolean')
            ->update();
        $this->table('CustomerVolumes')
            ->addColumn('do_not_bill', 'boolean')
            ->update();
        $this->table('InvoiceVolumes')
            ->addColumn('do_not_bill', 'boolean')
            ->update();
    }
}
