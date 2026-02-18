<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctSenderId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctAccounts')
            ->addColumn('sender_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('sender_password', 'string', ['length' => 678, 'null' => true, 'default' => null])
            ->update();
    }
}
