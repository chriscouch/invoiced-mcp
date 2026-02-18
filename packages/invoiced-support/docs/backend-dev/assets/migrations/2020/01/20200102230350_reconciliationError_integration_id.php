<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReconciliationErrorIntegrationId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ReconciliationErrors')
            ->addColumn('integration_id', 'integer', ['length' => 3, 'signed' => false])
            ->removeColumn('integration')
            ->addIndex('integration_id')
            ->update();
    }
}
