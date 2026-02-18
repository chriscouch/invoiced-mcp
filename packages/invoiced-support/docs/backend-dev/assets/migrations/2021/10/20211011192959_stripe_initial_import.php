<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class StripeInitialImport extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute("INSERT INTO Features SELECT NULL, tenant_id, 'stripe_initial_import', 1 FROM Imports WHERE type IN ('stripe_customer') GROUP BY tenant_id ");
    }
}
