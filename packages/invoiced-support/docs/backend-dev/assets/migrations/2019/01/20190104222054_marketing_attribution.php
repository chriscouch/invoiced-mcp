<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MarketingAttribution extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('MarketingAttributions');
        $table->addColumn('tenant_id', 'integer')
            ->addColumn('timestamp', 'timestamp')
            ->addColumn('utm_campaign', 'string')
            ->addColumn('utm_source', 'string')
            ->addColumn('utm_medium', 'string')
            ->addColumn('utm_term', 'string')
            ->addColumn('utm_content', 'string')
            ->addColumn('$initial_referrer', 'string')
            ->addColumn('$initial_referring_domain', 'string')
            ->create();
    }
}
