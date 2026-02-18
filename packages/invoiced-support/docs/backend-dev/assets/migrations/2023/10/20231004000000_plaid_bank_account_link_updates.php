<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PlaidBankAccountLinkUpdates extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PlaidBankAccountLinks')
            ->addColumn('verified', 'tinyinteger', ['default' => 1])
            ->addColumn('verification_public_token', 'string', ['length' => 64, 'null' => true, 'default' => null])
            ->update();
    }
}
