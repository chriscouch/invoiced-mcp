<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NullableShipToName extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ShippingDetails')
            ->changeColumn('name', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
