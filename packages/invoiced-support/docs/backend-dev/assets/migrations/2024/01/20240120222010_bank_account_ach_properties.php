<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BankAccountAchProperties extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BankAccounts')
            ->addColumn('account_holder_type', 'enum', ['null' => true, 'values' => ['company', 'individual']])
            ->addColumn('account_holder_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('type', 'enum', ['null' => true, 'values' => ['checking', 'savings']])
            ->addColumn('account_number', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
