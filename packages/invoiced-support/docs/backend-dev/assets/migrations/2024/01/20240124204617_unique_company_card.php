<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class UniqueCompanyCard extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CompanyCards')
            ->addIndex(['tenant_id', 'stripe_payment_method'], ['unique' => true])
            ->update();
    }
}
