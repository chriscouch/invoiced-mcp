<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LobEnvelope extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('LobAccounts')
            ->addColumn('custom_envelope', 'string', ['null' => true, 'default' => null])
            ->update();
    }
}
