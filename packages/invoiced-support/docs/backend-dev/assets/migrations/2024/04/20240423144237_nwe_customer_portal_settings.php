<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NweCustomerPortalSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('CustomerPortalSettings')
            ->addColumn('enabled', 'boolean')
            ->addColumn('include_sub_customers', 'boolean')
            ->addColumn('show_powered_by', 'boolean')
            ->addColumn('require_authentication', 'boolean')
            ->addColumn('allow_editing_contacts', 'boolean')
            ->addColumn('invoice_payment_to_item_selection', 'boolean')
            ->addColumn('welcome_message', 'text')
            ->update();
    }
}
