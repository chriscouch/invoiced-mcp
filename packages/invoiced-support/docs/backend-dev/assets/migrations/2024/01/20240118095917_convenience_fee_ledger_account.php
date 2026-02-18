<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ConvenienceFeeLedgerAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute("INSERT IGNORE INTO Accounts 
            SELECT null, 'Convenience Fees', account_type, ledger_id, currency_id, null
            FROM Accounts
            WHERE name = 'Purchases'");
    }
}
