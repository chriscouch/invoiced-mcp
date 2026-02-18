<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentClientId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Payments')
            ->addColumn('client_id', 'string', ['length' => 24])
            ->addColumn('client_id_exp', 'integer')
            ->addIndex('client_id_exp')
            ->update();
    }
}
