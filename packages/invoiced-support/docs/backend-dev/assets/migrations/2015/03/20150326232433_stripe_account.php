<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class StripeAccount extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('StripeAccounts', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('user_id', 'string')
            ->addColumn('name', 'string', ['null' => true, 'default' => null])
            ->addColumn('access_token_enc', 'string', ['length' => 678])
            ->addColumn('refresh_token_enc', 'string', ['length' => 678])
            ->addColumn('publishable_key', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
