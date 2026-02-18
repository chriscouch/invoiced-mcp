<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class DisbursementRecipientId extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('FlywireDisbursements')
            ->renameColumn('destination_code', 'recipient_id')
            ->update();
    }
}
