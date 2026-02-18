<?php

use App\Core\Multitenant\MultitenantModelMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class Report extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Reports');
        $this->addTenant($table);
        $table->addColumn('type', 'string')
            ->addColumn('timestamp', 'integer')
            ->addColumn('title', 'string')
            ->addColumn('data', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('csv_url', 'string')
            ->addColumn('pdf_url', 'string')
            ->addTimestamps()
            ->save();
    }
}
