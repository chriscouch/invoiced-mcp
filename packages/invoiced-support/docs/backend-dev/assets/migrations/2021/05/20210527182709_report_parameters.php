<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReportParameters extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Reports')
            ->addColumn('parameters', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
