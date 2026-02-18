<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MrrVersionSettingFk extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('SubscriptionBillingSettings')
            ->dropForeignKey('mrr_version_id')
            ->update();

        $this->table('SubscriptionBillingSettings')
            ->addForeignKey('mrr_version_id', 'MrrVersions', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->update();
    }
}
