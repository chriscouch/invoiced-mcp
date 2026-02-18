<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UpdatePaymentsMatchedProperty extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('Payments')
            ->changeColumn('matched', 'boolean', ['null' => true, 'default' => null])
            ->update();
    }
}
