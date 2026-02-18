<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ChasingCadenceDays extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('ChasingCadences')
            ->addColumn('run_days', 'string', ['length' => 255, 'null' => true, 'default' => null])
            ->update();
    }
}
