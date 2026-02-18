<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AchFileFormat extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AchFileFormats');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('immediate_destination', 'string')
            ->addColumn('immediate_destination_name', 'string')
            ->addColumn('immediate_origin', 'string')
            ->addColumn('immediate_origin_name', 'string')
            ->addColumn('company_name', 'string')
            ->addColumn('company_id', 'string')
            ->addColumn('company_discretionary_data', 'string')
            ->addColumn('company_entry_description', 'string')
            ->addColumn('originating_dfi_identification', 'string')
            ->addColumn('default_sec_code', 'string')
            ->addTimestamps()
            ->create();
    }
}
