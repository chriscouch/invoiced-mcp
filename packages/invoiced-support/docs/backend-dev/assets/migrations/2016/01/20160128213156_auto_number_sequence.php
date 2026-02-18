<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AutoNumberSequence extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('AutoNumberSequences', ['id' => false, 'primary_key' => ['tenant_id', 'type']]);
        $this->addTenant($table);
        $table->addColumn('type', 'enum', ['values' => ['credit_note', 'customer', 'estimate', 'invoice']])
            ->addColumn('template', 'string')
            ->addColumn('next', 'integer', ['default' => 1])
            ->create();
    }
}
