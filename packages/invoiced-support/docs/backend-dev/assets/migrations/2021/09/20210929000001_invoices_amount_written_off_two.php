<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InvoicesAmountWrittenOffTwo extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('UPDATE Invoices SET amount_written_off = balance WHERE status="bad_debt" AND amount_written_off = 0');
    }
}
