<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class TextAndLetterRelations extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('TextMessages')
            ->addColumn('contact_name', 'string', ['null' => true, 'default' => null])
            ->addColumn('related_to_type', 'integer', ['null' => true, 'default' => null])
            ->addColumn('related_to_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['tenant_id', 'related_to_type', 'related_to_id'])
            ->update();

        $this->table('Letters')
            ->addColumn('related_to_type', 'integer', ['null' => true, 'default' => null])
            ->addColumn('related_to_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['tenant_id', 'related_to_type', 'related_to_id'])
            ->update();
    }
}
