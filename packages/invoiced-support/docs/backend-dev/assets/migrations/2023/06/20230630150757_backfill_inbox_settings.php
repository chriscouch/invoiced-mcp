<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillInboxSettings extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('UPDATE AccountsReceivableSettings SET inbox_id=(SELECT id FROM Inboxes WHERE tenant_id=AccountsReceivableSettings.tenant_id LIMIT 1)');
    }
}
