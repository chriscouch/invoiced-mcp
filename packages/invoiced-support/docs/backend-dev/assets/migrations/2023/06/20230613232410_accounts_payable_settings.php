<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class AccountsPayableSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('AccountsPayableSettings', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('aging_buckets', 'string', ['default' => '[0,7,14,30,60]'])
            ->addColumn('aging_date', 'enum', ['values' => ['date', 'due_date'], 'default' => 'date'])
            ->addTimestamps()
            ->create();
    }
}
