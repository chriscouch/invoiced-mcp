<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class Vendor extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('Vendors');
        $this->addTenant($table);
        $table->addColumn('name', 'string')
            ->addColumn('number', 'string', ['length' => 32])
            ->addColumn('active', 'boolean')
            ->addColumn('network_connection_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['tenant_id', 'number'], ['unique' => true, 'name' => 'unique_number'])
            ->addForeignKey('network_connection_id', 'NetworkConnections', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addTimestamps()
            ->create();

        $this->table('AutoNumberSequences')
            ->changeColumn('type', 'enum', ['values' => ['credit_note', 'customer', 'estimate', 'invoice', 'vendor']])
            ->update();

        $this->execute('INSERT INTO AutoNumberSequences (tenant_id, type, template, next) SELECT id, "vendor", "VEND-%05d", 1 FROM Companies');
    }
}
