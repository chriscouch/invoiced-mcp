<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicedPaymentsFeatureFlag extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->execute('INSERT INTO Features (tenant_id,feature,value) SELECT id,"invoiced_payments",1 as `value` FROM Companies WHERE EXISTS (SELECT 1 FROM MerchantAccounts WHERE MerchantAccounts.tenant_id=Companies.id AND gateway="invoiced")');
    }
}
