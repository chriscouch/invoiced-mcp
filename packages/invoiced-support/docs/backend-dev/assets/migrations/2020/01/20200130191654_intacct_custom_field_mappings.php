<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctCustomFieldMappings extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('customer_custom_field_mapping', 'text', ['default' => null, 'null' => true])
            ->addColumn('invoice_custom_field_mapping', 'text', ['default' => null, 'null' => true])
            ->update();
    }
}
