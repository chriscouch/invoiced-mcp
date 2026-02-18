<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RenameVendorBankAccounts extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorBankAccounts')
            ->rename('CompanyBankAccounts')
            ->update();
    }
}
