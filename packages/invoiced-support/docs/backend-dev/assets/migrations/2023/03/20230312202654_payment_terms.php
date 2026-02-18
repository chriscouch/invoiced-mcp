<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class PaymentTerms extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('PaymentTerms');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('due_in_days', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('discount_is_percent', 'boolean', ['null' => true, 'default' => null])
            ->addColumn('discount_value', 'decimal', ['precision' => 20, 'scale' => 10, 'null' => true, 'default' => null])
            ->addColumn('discount_expires_in_days', 'smallinteger', ['null' => true, 'default' => null])
            ->addColumn('active', 'boolean')
            ->addTimestamps()
            ->addIndex(['tenant_id', 'name'], ['unique' => true])
            ->create();
    }
}
