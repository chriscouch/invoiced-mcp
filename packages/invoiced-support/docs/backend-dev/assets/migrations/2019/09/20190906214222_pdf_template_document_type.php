<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class PdfTemplateDocumentType extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('PdfTemplates')
            ->addColumn('document_type', 'enum', ['values' => ['invoice', 'credit_note', 'estimate', 'receipt', 'statement'], 'default' => 'invoice'])
            ->update();
    }
}
