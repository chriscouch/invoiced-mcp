<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailVerificationIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CompanyEmailAddresses')
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['tenant_id', 'email'], ['unique' => true])
            ->update();

        $this->table('CompanyTaxIds')
            ->addColumn('name', 'string')
            ->update();
    }
}
