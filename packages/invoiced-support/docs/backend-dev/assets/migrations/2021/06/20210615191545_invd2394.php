<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class Invd2394 extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('UPDATE Invoices SET closed=0, paid=0, date_paid=NULL WHERE voided=1');
        $this->execute('UPDATE CreditNotes SET closed=0, paid=0 WHERE voided=1');
    }
}
