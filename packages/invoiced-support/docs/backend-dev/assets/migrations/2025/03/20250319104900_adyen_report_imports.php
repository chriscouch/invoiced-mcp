<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AdyenReportImports extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AdyenReportImports');
        $table->addColumn('file', 'string')
            ->addColumn('report_type', 'string')
            ->addColumn('processed', 'tinyinteger')
            ->addIndex(['file'], ['unique' => true, 'name' => 'unique_number'])
            ->addTimestamps()
            ->create();
    }
}
