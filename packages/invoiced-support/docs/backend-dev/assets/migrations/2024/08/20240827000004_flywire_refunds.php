<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FlywireRefunds extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('FlywireRefunds');
        $this->addTenant($table);
        $table->addColumn('payment_id', 'string')
            ->addColumn('refund_id', 'string')
            ->addColumn('bundle_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('amount', 'integer')
            ->addColumn('currency', 'string')
            ->addColumn('status', 'smallinteger')
            ->addColumn('ar_refund_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addIndex('refund_id', ['unique' => true])
            ->addForeignKey('ar_refund_id', 'Refunds', 'id')
            ->create();
    }
}
