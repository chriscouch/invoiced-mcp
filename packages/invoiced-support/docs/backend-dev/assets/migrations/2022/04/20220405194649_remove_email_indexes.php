<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveEmailIndexes extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Emails')->removeIndex('tracking_id')->update();
        $this->table('EmailOpens')->removeIndex('timestamp')->update();
    }
}
