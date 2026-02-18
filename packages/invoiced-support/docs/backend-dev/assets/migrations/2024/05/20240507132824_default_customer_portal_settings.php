<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DefaultCustomerPortalSettings extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE CustomerPortalSettings SET enabled=1');
        $this->execute('UPDATE CustomerPortalSettings SET include_sub_customers=1');
        $this->execute('UPDATE CustomerPortalSettings SET show_powered_by=1');
        $this->execute('UPDATE CustomerPortalSettings SET invoice_payment_to_item_selection=1');
    }
}
