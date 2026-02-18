<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CreateWorkatoAccountsTable extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('WorkatoAccounts');
        $this->addTenant($table);
        $table->addColumn('customer_id', 'string', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}