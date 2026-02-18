<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class FixSucharging extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('Customers');

        $column = $table->hasColumn('sucharging');
        if ($column) {
            $table->removeColumn('sucharging')
                ->update();
        }

        $column = $table->hasColumn('surcharging');
        if (!$column) {
            $table->addColumn('surcharging', 'boolean', ['default' => true])
                ->update();
        }
    }
}
