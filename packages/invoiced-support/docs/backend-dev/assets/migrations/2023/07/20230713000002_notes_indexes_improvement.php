<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class NotesIndexesImprovement extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Notes')
            ->addIndex(['invoice_id', 'tenant_id'])
            ->addIndex(['customer_id', 'tenant_id'])
            ->update();
    }
}
