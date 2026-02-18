<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddPlaidIdFieldToUnappliedPayments extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('UnappliedPayments')
            ->addColumn('plaid_id', 'string', ['default' => null, 'null' => true])
            ->addColumn('sender_id', 'string', ['default' => null, 'null' => true])
            ->addColumn('payee', 'string', ['default' => null, 'null' => true])
            ->update();
    }
}
