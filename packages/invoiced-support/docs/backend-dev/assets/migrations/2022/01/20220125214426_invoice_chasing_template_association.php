<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoiceChasingTemplateAssociation extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InvoiceDeliveries')
            ->addColumn('cadence_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['tenant_id', 'cadence_id'])
            ->update();
    }
}
