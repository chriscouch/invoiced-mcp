<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorPayment extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('VendorPayments');
        $this->addTenant($table);
        $table->addColumn('vendor_id', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('payment_method', 'string', ['length' => 32])
            ->addColumn('reference', 'string')
            ->addColumn('notes', 'string')
            ->addColumn('status', 'integer')
            ->addColumn('expected_arrival_date', 'date', ['null' => true, 'default' => null])
            ->addColumn('voided', 'boolean')
            ->addColumn('date_voided', 'date', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('vendor_id', 'Vendors', 'id')
            ->create();

        $table = $this->table('VendorPaymentItems');
        $this->addTenant($table);
        $table->addColumn('vendor_payment_id', 'integer')
            ->addColumn('network_document_id', 'integer')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addTimestamps()
            ->addForeignKey('vendor_payment_id', 'VendorPayments', 'id', ['delete' => 'cascade', 'update' => 'cascade'])
            ->addForeignKey('network_document_id', 'NetworkDocuments', 'id')
            ->create();
    }
}
