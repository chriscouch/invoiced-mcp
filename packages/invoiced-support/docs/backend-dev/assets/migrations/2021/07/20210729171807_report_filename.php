<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReportFilename extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Reports')
            ->addColumn('filename', 'string')
            ->update();
    }
}
