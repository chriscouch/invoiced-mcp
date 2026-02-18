<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailTemplate extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EmailTemplates', ['id' => false, 'primary_key' => ['tenant_id', 'id']]);
        $this->addTenant($table);
        $table->addColumn('id', 'string')
            ->addColumn('name', 'string')
            ->addColumn('type', 'enum', ['values' => ['invoice', 'credit_note', 'payment_plan', 'estimate', 'subscription', 'transaction', 'statement', 'chasing']])
            ->addColumn('subject', 'string')
            ->addColumn('body', 'text')
            ->addColumn('language', 'string', ['null' => true, 'default' => null, 'length' => 2])
            ->addTimestamps()
            ->addIndex('language')
            ->addIndex('type')
            ->save();
    }
}
