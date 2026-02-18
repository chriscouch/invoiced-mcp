<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NullCashApplicationBankAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('BankFeedTransactions')
            ->changeColumn('cash_application_bank_account_id', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
