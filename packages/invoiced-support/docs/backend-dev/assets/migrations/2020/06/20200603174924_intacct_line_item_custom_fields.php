<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctLineItemCustomFields extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('line_item_custom_field_mapping', 'text', ['default' => null, 'null' => true])
            ->update();
    }
}
