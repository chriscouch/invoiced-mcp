<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentInstructionsMetaNullable extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PaymentInstructions')
            ->changeColumn('meta', 'text', ['null' => true])
            ->update();
    }
}
