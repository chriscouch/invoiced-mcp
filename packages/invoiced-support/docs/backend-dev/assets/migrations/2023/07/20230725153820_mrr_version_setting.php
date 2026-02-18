<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MrrVersionSetting extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('SubscriptionBillingSettings')
            ->addColumn('mrr_version_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('mrr_version_id', 'MrrVersions', 'id')
            ->update();
    }
}
