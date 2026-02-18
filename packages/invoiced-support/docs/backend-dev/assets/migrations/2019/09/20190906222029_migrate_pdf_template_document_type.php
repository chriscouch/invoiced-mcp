<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class MigratePdfTemplateDocumentType extends MultitenantModelMigration
{
    public function change()
    {
        $this->execute('update PdfTemplates set document_type="invoice" where name like "%invoice"');
        $this->execute('update PdfTemplates set document_type="receipt" where name like "%receipt"');
        $this->execute('update PdfTemplates set document_type="statement" where name like "%statement"');
        $this->execute('update PdfTemplates set document_type="estimate" where name like "%estimate"');
        $this->execute('update Themes set estimate_template_id=null, invoice_template_id=null, receipt_template_id=null, statement_template_id=null where custom_appearance=0');
    }
}
