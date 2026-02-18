<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class CustomerPaymentBatch extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('CustomerPaymentBatches');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('number', 'string', ['length' => 32])
            ->addColumn('ach_file_format_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('status', 'tinyinteger')
            ->addColumn('payment_method', 'string')
            ->addTimestamps()
            ->addForeignKey('ach_file_format_id', 'AchFileFormats', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->create();

        $table = $this->table('CustomerPaymentBatchItems');
        $this->addTenant($table);
        $table->addColumn('customer_payment_batch_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('charge_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('customer_payment_batch_id', 'CustomerPaymentBatches', 'id')
            ->addForeignKey('charge_id', 'Charges', 'id')
            ->create();
    }
}
