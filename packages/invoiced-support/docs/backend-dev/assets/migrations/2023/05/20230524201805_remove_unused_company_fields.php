<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveUnusedCompanyFields extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Companies')
            ->removeColumn('plan')
            ->removeColumn('renews_next')
            ->removeColumn('past_due')
            ->removeColumn('stripe_customer')
            ->removeColumn('decimal_format')
            ->removeColumn('bcc')
            ->removeColumn('referred_by')
            ->removeColumn('billing_system')
            ->removeColumn('invoiced_customer')
            ->removeColumn('reseller_id')
            ->update();
    }
}
