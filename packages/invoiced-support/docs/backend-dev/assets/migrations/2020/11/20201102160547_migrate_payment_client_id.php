<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MigratePaymentClientId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Payments')
            ->addIndex('client_id', ['unique' => true])
            ->update();
    }
}
