<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentInstructionsEnabled extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('PaymentInstructions');
        $table->addColumn('enabled', 'boolean')
            ->update();
    }
}
