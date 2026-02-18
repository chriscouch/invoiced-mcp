<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ChargeMerchantAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableForeignKeyChecks();
        $this->table('Charges')
            ->addColumn('merchant_account_id', 'integer', ['default' => null, 'null' => true])
            ->addForeignKey('merchant_account_id', 'MerchantAccounts', 'id')
            ->update();
        $this->enableForeignKeyChecks();
    }
}
