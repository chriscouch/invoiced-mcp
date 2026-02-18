<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PersistentSessionId extends MultitenantModelMigration
{
    public function change()
    {
        // Drop primary key
        $this->execute('ALTER TABLE PersistentSessions DROP PRIMARY KEY');

        // Add auto-increment primary key
        $this->execute('ALTER TABLE PersistentSessions ADD id INT AUTO_INCREMENT PRIMARY KEY');
    }
}
