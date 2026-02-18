<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CanceledCompanyBillingProfile extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CanceledCompanies')
            ->addColumn('billing_profile_id', 'integer', ['default' => null, 'null' => true])
            ->update();
    }
}
