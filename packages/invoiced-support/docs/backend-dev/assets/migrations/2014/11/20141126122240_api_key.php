<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ApiKey extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('ApiKeys');
        $this->addTenant($table);
        $table->addColumn('description', 'string', ['null' => true, 'default' => null])
            ->addColumn('protected', 'boolean')
            ->addColumn('user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('source', 'string', ['length' => 20])
            ->addColumn('expires', 'integer', ['null' => true, 'default' => null])
            ->addColumn('remember_me', 'boolean')
            ->addColumn('last_used', 'integer', ['null' => null, 'default' => null])
            ->addColumn('secret_enc', 'string', ['length' => 232])
            ->addColumn('secret_hash', 'string', ['length' => 128])
            ->addTimestamps()
            ->addIndex('source')
            ->addIndex('protected')
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
