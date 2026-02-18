<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ApBankAccountSignature extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorBankAccounts')
            ->changeColumn('signature', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
