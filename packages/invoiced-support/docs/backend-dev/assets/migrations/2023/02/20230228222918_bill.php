<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Bill extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Bills');
        $this->addTenant($table);
        $table->addColumn('vendor_id', 'integer')
            ->addColumn('date', 'date')
            ->addColumn('number', 'string')
            ->addColumn('currency', 'string', ['length' => 3])
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('status', 'smallinteger')
            ->addColumn('voided', 'boolean')
            ->addColumn('date_voided', 'date', ['null' => true, 'default' => null])
            ->addColumn('network_document_id', 'integer', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('vendor_id', 'Vendors', 'id')
            ->addForeignKey('network_document_id', 'NetworkDocuments', 'id')
            ->create();
    }
}
