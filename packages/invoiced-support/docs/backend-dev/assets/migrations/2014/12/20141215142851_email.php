<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Email extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Emails', ['id' => false, 'primary_key' => ['id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string')
            ->addColumn('state', 'enum', ['values' => ['sent', 'scheduled', 'queued', 'invalid', 'deferred', 'rejected', 'soft-bounced', 'bounced']])
            ->addColumn('reject_reason', 'string', ['null' => true, 'default' => null])
            ->addColumn('email', 'string', ['length' => 1000])
            ->addColumn('template', 'string')
            ->addColumn('subject', 'string')
            ->addColumn('message_compressed', 'blob')
            ->addColumn('tracking_id', 'string', ['length' => 32])
            ->addColumn('customer_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('opens', 'integer')
            ->addColumn('clicks', 'integer')
            ->addTimestamps()
            ->addIndex('tracking_id', ['unique' => true])
            ->save();
    }
}
