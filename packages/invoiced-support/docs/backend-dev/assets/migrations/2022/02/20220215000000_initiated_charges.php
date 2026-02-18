<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InitiatedCharges extends MultitenantModelMigration
{
    public function change()
    {
        $table = $this->table('InitiatedCharges');
        $this->addTenant($table);
        $table->addTimestamps()
            ->addColumn('correlation_id', 'string', ['length' => 255])
            ->addColumn('gateway', 'string')
            ->addColumn('parameters', 'json', ['default' => '[]]', 'null' => false])
            ->addColumn('amount', 'decimal', ['precision' => 20, 'scale' => 10])
            ->addColumn('currency', 'string', ['length' => 3, 'null' => true, 'default' => null])
            ->addColumn('charge', 'json', ['default' => '{}', 'null' => false])
            ->create();

        $table = $this->table('InitiatedChargeDocuments');
        $this->addTenant($table);
        $table->addColumn('initiated_charge_id', 'integer')
            ->addColumn('document_type', 'smallinteger')
            ->addColumn('document_id', 'integer')
            ->addIndex('document_id')
            ->addForeignKey('initiated_charge_id', 'InitiatedCharges', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
