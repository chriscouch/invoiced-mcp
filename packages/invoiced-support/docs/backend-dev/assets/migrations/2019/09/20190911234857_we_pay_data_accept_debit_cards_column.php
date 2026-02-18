<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class WePayDataAcceptDebitCardsColumn extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('WePayData')
            ->addColumn('accept_debit_cards', 'boolean', ['null' => true, 'default' => null])
            ->update();
    }
}
