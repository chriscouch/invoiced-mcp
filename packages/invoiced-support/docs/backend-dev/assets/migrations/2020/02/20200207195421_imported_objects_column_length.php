<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ImportedObjectsColumnLength extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('ImportedObjects')
            ->changeColumn('object', 'string', ['length' => 30])
            ->update();
    }
}
