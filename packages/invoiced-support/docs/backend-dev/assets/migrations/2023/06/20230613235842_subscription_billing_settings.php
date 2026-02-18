<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionBillingSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('SubscriptionBillingSettings', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('after_subscription_nonpayment', 'enum', ['values' => ['cancel', 'do_nothing'], 'default' => 'cancel'])
            ->addColumn('subscription_draft_invoices', 'boolean', ['default' => false])
            ->addTimestamps()
            ->create();
    }
}
