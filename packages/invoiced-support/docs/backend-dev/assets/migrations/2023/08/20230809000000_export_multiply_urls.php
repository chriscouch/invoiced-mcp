<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ExportMultiplyUrls extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Exports')
            ->changeColumn('download_url', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
