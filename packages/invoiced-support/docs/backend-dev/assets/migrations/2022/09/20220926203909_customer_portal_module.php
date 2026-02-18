<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPortalModule extends MultitenantModelMigration
{
    public function change(): void
    {
        // Customer Portal
        // Enable for all users of the collections module.
        $this->execute('INSERT INTO Features (tenant_id,feature,value) SELECT tenant_id,"module_customer_portal",1 as `value` FROM Features WHERE feature="module_collections"');
    }
}
