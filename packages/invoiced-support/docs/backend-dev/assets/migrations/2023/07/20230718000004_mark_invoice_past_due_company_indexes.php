<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MarkInvoicePastDueCompanyIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Companies')
            ->addIndex('canceled')
            ->update();
    }
}
