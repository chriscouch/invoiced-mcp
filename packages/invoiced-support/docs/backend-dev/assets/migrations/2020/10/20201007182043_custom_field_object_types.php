<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomFieldObjectTypes extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CustomFields')
            ->changeColumn('object', 'enum', ['null' => true, 'default' => null, 'values' => ['customer', 'invoice', 'credit_note', 'estimate', 'line_item', 'subscription', 'transaction', 'plan', 'item']])
            ->update();
    }
}
