<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class RemoveAttachmentIndex extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->disableMaxStatementTimeout();
        $this->table('Attachments')
            ->removeIndex('location')
            ->addIndex(['parent_id'])
            ->update();
    }
}
