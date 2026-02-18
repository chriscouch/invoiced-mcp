<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesDateIndexDrop extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Invoices')
            ->removeIndex(['date'])
            ->update();
    }
}
