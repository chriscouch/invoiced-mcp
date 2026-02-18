<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DwollaDocumentRequest extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('DwollaDocumentRequests');
        $this->addTenant($table);
        $table->addColumn('document_type', 'smallinteger')
            ->addColumn('beneficial_owner_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('dwolla_document_id', 'string', ['null' => true, 'default' => null])
            ->addColumn('completed', 'boolean')
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->addForeignKey('beneficial_owner_id', 'DwollaBeneficialOwners', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
