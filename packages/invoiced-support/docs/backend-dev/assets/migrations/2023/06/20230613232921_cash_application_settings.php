<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CashApplicationSettings extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CashApplicationSettings', ['id' => false, 'primary_key' => ['tenant_id']]);
        $this->addTenant($table);
        $table->addColumn('short_pay_units', 'enum', ['values' => ['percent', 'dollars'], 'default' => 'percent'])
            ->addColumn('short_pay_amount', 'integer', ['default' => 10])
            ->addTimestamps()
            ->create();
    }
}
