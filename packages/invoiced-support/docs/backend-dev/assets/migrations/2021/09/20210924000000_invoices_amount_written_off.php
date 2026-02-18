<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesAmountWrittenOff extends MultitenantModelMigration
{
    public function change()
    {
        $this->ensureInstant();
        $this->table('Invoices')
            ->addColumn('amount_written_off', 'decimal', ['precision' => 20, 'scale' => 10])
            ->update();
        $this->ensureInstantEnd();
        $this->execute('UPDATE Invoices SET amount_written_off = balance WHERE date_bad_debt > 0');
    }
}
