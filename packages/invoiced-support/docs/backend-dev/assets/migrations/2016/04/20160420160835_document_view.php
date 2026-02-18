<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class DocumentView extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('DocumentViews');
        $this->addTenant($table);
        $table->addColumn('document_type', 'enum', ['values' => ['credit_note', 'estimate', 'invoice']])
            ->addColumn('document_id', 'integer')
            ->addColumn('timestamp', 'integer')
            ->addColumn('user_agent', 'string')
            ->addColumn('ip', 'string', ['length' => 45])
            ->addIndex('document_type')
            ->addIndex('document_id')
            ->create();
    }
}
