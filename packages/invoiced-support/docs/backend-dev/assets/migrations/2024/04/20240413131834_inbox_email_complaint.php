<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class InboxEmailComplaint extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('InboxEmails')
            ->addColumn('bounce', 'boolean')
            ->addColumn('complaint', 'boolean')
            ->update();
    }
}
