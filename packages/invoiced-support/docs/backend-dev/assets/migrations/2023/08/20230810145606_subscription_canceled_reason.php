<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class SubscriptionCanceledReason extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->ensureInstant();
        $this->table('Subscriptions')
            ->addColumn('canceled_reason', 'string', ['null' => true, 'default' => null])
            ->update();
        $this->ensureInstantEnd();
    }
}
