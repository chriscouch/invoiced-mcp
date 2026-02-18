<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class AddNotesToUnappliedPayments extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('UnappliedPayments')
            ->addColumn('notes', 'text', ['null' => true, 'default' => null])
            ->update();
    }
}
