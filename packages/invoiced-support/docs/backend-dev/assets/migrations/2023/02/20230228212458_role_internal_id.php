<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RoleInternalId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('Roles')
            ->addIndex(['tenant_id', 'id'], ['unique' => true])
            ->update();
        $this->execute('ALTER TABLE Roles DROP PRIMARY KEY');
        $this->execute('ALTER TABLE Roles ADD internal_id INT AUTO_INCREMENT PRIMARY KEY');
    }
}
