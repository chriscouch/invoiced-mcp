<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkQueuedSend extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('NetworkQueuedSends');
        $this->addTenant($table);
        $table->addColumn('object_type', 'integer')
            ->addColumn('object_id', 'integer')
            ->addColumn('customer_id', 'integer')
            ->addColumn('member_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
