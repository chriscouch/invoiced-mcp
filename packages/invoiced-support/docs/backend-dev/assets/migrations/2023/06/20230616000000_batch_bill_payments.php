<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BatchBillPayments extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('BatchBillPayments');
        $this->addTenant($table);
        $table->addColumn('member_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('status', 'tinyinteger')
            ->addTimestamps()
            ->addForeignKey('member_id', 'Members', 'id', ['update' => 'SET NULL', 'delete' => 'SET NULL'])
            ->create();

        $table = $this->table('BatchBillPaymentBills');
        $this->addTenant($table);
        $table->addColumn('batch_id', 'integer')
            ->addColumn('bill_id', 'integer')
            ->addColumn('check_number', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['batch_id', 'bill_id'], ['unique' => true])
            ->addIndex(['check_number', 'tenant_id'], ['unique' => true])
            ->addForeignKey('batch_id', 'BatchBillPayments', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addForeignKey('bill_id', 'Bills', 'id')
            ->create();
    }
}
