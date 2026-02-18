<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Webhook extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Webhooks');
        $this->addTenant($table);
        $table->addColumn('url', 'string')
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('protected', 'boolean')
            ->addColumn('secret_enc', 'string', ['length' => 232, 'null' => true, 'default' => null])
            ->addColumn('events', 'string', ['default' => '["*"]', 'length' => 1000])
            ->addTimestamps()
            ->create();
    }
}
