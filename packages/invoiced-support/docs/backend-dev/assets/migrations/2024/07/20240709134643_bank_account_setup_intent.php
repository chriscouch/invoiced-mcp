<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BankAccountSetupIntent extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('BankAccounts')
            ->addColumn('gateway_setup_intent', 'string', ['null' => true, 'default' => null])
            ->update();
        $this->table('Cards')
            ->addColumn('gateway_setup_intent', 'string', ['null' => true, 'default' => null])
            ->update();
        $this->ensureInstantEnd();

        $this->table('BankAccounts')
            ->addIndex('gateway_setup_intent')
            ->update();
        $this->table('Cards')
            ->addIndex('gateway_setup_intent')
            ->update();
    }
}
