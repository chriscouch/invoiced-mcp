<?php

use Phinx\Migration\AbstractMigration;

final class ImportedObjects extends AbstractMigration
{
    public function change()
    {
        $this->table('ImportedObjects', ['id' => false, 'primary_key' => ['import', 'object', 'object_id']])
            ->addColumn('import', 'integer')
            ->addColumn('object', 'string', ['length' => 20])
            ->addColumn('object_id', 'string')
            ->addForeignKey('import', 'Imports', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
