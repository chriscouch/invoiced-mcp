<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillInvoiceNetworkDocuments extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE NetworkDocuments d JOIN Invoices i ON i.tenant_id=d.from_company_id AND i.number=d.reference SET i.network_document_id=d.id WHERE i.network_document_id IS NULL');
        $this->execute('UPDATE NetworkDocuments d JOIN Estimates i ON i.tenant_id=d.from_company_id AND i.number=d.reference SET i.network_document_id=d.id WHERE i.network_document_id IS NULL');
        $this->execute('UPDATE NetworkDocuments d JOIN CreditNotes i ON i.tenant_id=d.from_company_id AND i.number=d.reference SET i.network_document_id=d.id WHERE i.network_document_id IS NULL');
    }
}
