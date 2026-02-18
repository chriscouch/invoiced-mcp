<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CompanyPhoneNumber extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CompanyPhoneNumbers');
        $this->addTenant($table);
        $table->addColumn('phone', 'string')
            ->addColumn('channel', 'smallinteger')
            ->addColumn('verified_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
