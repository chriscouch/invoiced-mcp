<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class ImproveCreditNoteIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('CreditNotes')
            ->removeIndex('date')
            ->removeIndex('draft')
            ->removeIndex('paid')
            ->removeIndex('sent')
            ->removeIndex('status')
            ->removeIndex('viewed')
            ->removeIndex('voided')
            ->addIndex(['tenant_id', 'status'])
            ->update();
    }
}
