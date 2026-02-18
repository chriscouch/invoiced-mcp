<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class InitiatedChargeDocumentsDocumentId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('InitiatedChargeDocuments')
            ->changeColumn('document_id', 'decimal', ['null' => true, 'default' => null, 'precision' => 20, 'scale' => 10])
            ->update();
    }
}
