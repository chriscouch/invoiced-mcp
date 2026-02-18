<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class ContactRoles extends MultitenantModelMigration
{
    public function change()
    {
        $roles = $this->table('ContactRoles');
        $this->addTenant($roles);
        $roles->addColumn('name', 'string');
        $roles->addTimestamps();
        $roles->create();

        $this->table('Contacts')
            ->addColumn('role_id', 'integer', ['null' => true])
            ->addForeignKey('role_id', 'ContactRoles', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();

        $this->table('ChasingCadenceSteps')
            ->addColumn('role_id', 'integer', ['null' => true])
            ->addForeignKey('role_id', 'ContactRoles', 'id', ['delete' => 'SET NULL', 'update' => 'CASCADE'])
            ->update();
    }
}
