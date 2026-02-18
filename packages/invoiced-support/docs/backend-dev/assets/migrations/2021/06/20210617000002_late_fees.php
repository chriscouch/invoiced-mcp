<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class LateFees extends MultitenantModelMigration
{
    public function change()
    {
        $this->disableMaxStatementTimeout();

        $table = $this->table('LateFeeSchedules');
        $this->addTenant($table);
        $table->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('start_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('name', 'string')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('is_percent', 'boolean')
            ->addColumn('grace_period', 'integer')
            // 0 - not recurring
            ->addColumn('recurring_days', 'smallinteger')
            ->addColumn('default', 'boolean')
            ->create();

        $this->table('Customers')
            ->addColumn('late_fee_schedule_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('late_fee_schedule_id', 'LateFeeSchedules', 'id')
            ->update();

        $this->table('Invoices')
            ->addColumn('late_fees', 'boolean', ['default' => true])
            ->update();
    }
}
