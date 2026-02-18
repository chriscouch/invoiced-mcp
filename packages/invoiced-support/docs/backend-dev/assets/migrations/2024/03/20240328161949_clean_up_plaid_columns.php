<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CleanUpPlaidColumns extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PlaidBankAccountLinks')
            ->removeColumn('last_retrieved_data_at')
            ->removeColumn('data_starts_at')
            ->update();
    }
}
