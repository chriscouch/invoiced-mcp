<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NetworkDocuments extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('NetworkDocuments')
            ->addColumn('uuid', 'string')
            ->addColumn('from_company_id', 'integer', ['null' => true])
            ->addColumn('to_company_id', 'integer', ['null' => true])
            ->addColumn('type', 'smallinteger')
            ->addColumn('reference', 'string')
            ->addColumn('currency', 'string', ['length' => 3, 'default' => null, 'null' => true])
            ->addColumn('total', 'decimal', ['precision' => 20, 'scale' => 10, 'default' => null, 'null' => true])
            ->addColumn('format', 'smallinteger')
            ->addColumn('version', 'smallinteger')
            ->addColumn('current_status', 'smallinteger')
            ->addTimestamps()
            ->addForeignKey('from_company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addForeignKey('to_company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->create();

        $this->table('NetworkDocumentVersions')
            ->addColumn('document_id', 'integer')
            ->addColumn('version', 'smallinteger')
            ->addColumn('size', 'integer')
            ->addTimestamps()
            ->addForeignKey('document_id', 'NetworkDocuments', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->create();

        $this->table('NetworkDocumentStatusTransitions')
            ->addColumn('document_id', 'integer')
            ->addColumn('status', 'smallinteger')
            ->addColumn('effective_date', 'date')
            ->addColumn('company_id', 'integer', ['null' => true])
            ->addColumn('member_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('description', 'string', ['null' => true, 'default' => null])
            ->addForeignKey('company_id', 'Companies', 'id', ['update' => 'cascade', 'delete' => 'set null'])
            ->addForeignKey('document_id', 'NetworkDocuments', 'id', ['update' => 'cascade', 'delete' => 'cascade'])
            ->addTimestamps()
            ->create();
    }
}
