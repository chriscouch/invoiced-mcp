<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class NamespacedCustomFields extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CustomFields')
            ->addIndex(['tenant_id', 'object', 'id'], ['unique' => true])
            ->update();
        $this->execute('ALTER TABLE CustomFields DROP PRIMARY KEY');
        $this->execute('ALTER TABLE CustomFields ADD internal_id INT AUTO_INCREMENT PRIMARY KEY');
    }
}
