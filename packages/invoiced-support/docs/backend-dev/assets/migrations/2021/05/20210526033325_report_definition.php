<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ReportDefinition extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Reports')
            ->addColumn('definition', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
