<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DraftSubscriptionInvoicesSetting extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Settings')
            ->addColumn('subscription_draft_invoices', 'boolean', ['default' => false])
            ->update();
    }
}
