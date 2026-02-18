<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaAccountPlaidRemove extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('PlaidBankAccountLinks')
            ->removeColumn('verification_public_token')
            ->update();
    }
}
