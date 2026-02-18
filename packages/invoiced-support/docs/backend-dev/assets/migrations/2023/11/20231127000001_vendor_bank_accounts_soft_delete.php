<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorBankAccountsSoftDelete extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('VendorBankAccounts')
            ->changeColumn('check_number', 'integer', ['null' => true, 'default' => null])
            ->addColumn('deleted', 'boolean')
            ->addColumn('deleted_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
