<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CashApplicationChanges extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CashApplicationRules')
            ->addTimestamps()
            ->update();

        $this->table('BankFeedTransactions')
            ->renameColumn('name', 'description')
            ->update();
    }
}
