<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LateFeeDate extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('LateFees')
            ->addColumn('date', 'date', ['default' => null, 'null' => true])
            ->addColumn('version', 'tinyinteger', ['default' => 1])
            ->update();
    }
}
