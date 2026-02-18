<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class IntacctDraftInvoices extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('IntacctSyncProfiles')
            ->addColumn('read_invoices_as_drafts', 'boolean')
            ->update();
    }
}
