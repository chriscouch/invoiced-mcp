<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class VendorAdjustment extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('VendorAdjustments');
        $this->addTenant($table);
        $table->addColumn('vendor_id', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('notes', 'string')
            ->addColumn('network_document_id', 'integer')
            ->addColumn('voided', 'boolean')
            ->addColumn('date_voided', 'date', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('vendor_id', 'Vendors', 'id')
            ->addForeignKey('network_document_id', 'NetworkDocuments', 'id')
            ->create();
    }
}
