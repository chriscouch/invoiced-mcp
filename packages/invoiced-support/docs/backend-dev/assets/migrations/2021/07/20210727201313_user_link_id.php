<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class UserLinkId extends MultitenantModelMigration
{
    public function change()
    {
        // Remove user_id foreign key
        $this->table('UserLinks')
            ->dropForeignKey('user_id')
            ->update();

        // Drop primary key
        $this->execute('ALTER TABLE UserLinks DROP PRIMARY KEY');

        // Re-add user_id foreign key
        $this->table('UserLinks')
            ->addForeignKey('user_id', 'Users', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->update();

        // Add auto-increment primary key
        $this->execute('ALTER TABLE UserLinks ADD id INT AUTO_INCREMENT PRIMARY KEY');
    }
}
