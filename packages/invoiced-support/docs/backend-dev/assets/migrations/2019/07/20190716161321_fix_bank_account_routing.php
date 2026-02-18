<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class FixBankAccountRouting extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('BankAccounts')
            ->changeColumn('routing_number', 'string', ['length' => 9, 'null' => true, 'default' => null])
            ->update();
    }
}
