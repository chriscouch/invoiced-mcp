<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InitiatedChargeDocumentsAmount extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InitiatedCharges')
            ->addColumn('application_source', 'string', ['length' => 36])
            ->addColumn('source_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('customer_id', 'integer')
            ->update();

        $this->table('InitiatedChargeDocuments')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
    }
}
