<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class EmailTemplateOption extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('EmailTemplateOptions', ['id' => false, 'primary_key' => ['tenant_id', 'template', 'option']]);
        $this->addTenant($table);
        $table->addColumn('template', 'string')
            ->addColumn('option', 'string')
            ->addColumn('value', 'string')
            ->addTimestamps()
            ->save();
    }
}
