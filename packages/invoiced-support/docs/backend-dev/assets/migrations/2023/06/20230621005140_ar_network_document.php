<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ArNetworkDocument extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->disableForeignKeyChecks();
        $this->table('CreditNotes')
            ->addColumn('network_document_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('network_document_id', 'NetworkDocuments', 'id')
            ->update();

        $this->table('Estimates')
            ->addColumn('network_document_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('network_document_id', 'NetworkDocuments', 'id')
            ->update();

        $this->table('Invoices')
            ->addColumn('network_document_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('network_document_id', 'NetworkDocuments', 'id')
            ->update();
        $this->enableForeignKeyChecks();
    }
}
