<?php

use App\Core\Multitenant\MultitenantModelMigration;

final class CreditNoteConsolidatedInvoiceId extends MultitenantModelMigration
{
    public function change()
    {
        $this->table('CreditNotes')
            ->addColumn('consolidated_invoice_id', 'integer', ['null' => true, 'default' => null])
            ->addForeignKey('consolidated_invoice_id', 'Invoices', 'id', ['update' => 'CASCADE', 'delete' => 'SET NULL'])
            ->update();
    }
}
