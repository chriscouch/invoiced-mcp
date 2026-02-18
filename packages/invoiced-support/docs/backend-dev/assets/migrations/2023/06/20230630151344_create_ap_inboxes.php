<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;
use App\Core\Utils\RandomString;

final class CreateApInboxes extends MultitenantModelMigration
{
    public function up(): void
    {
        $rows = $this->fetchAll('SELECT tenant_id FROM AccountsPayableSettings WHERE inbox_id IS NULL');
        foreach ($rows as $row) {
            $externalId = RandomString::generate(10, 'abcdefghijklmnopqrstuvwxyz1234567890');
            $this->execute('INSERT INTO Inboxes (tenant_id, external_id) VALUES ('.$row['tenant_id'].', "'.$externalId.'")');
        }
        $this->execute('UPDATE AccountsPayableSettings SET inbox_id=(SELECT id FROM Inboxes WHERE tenant_id=AccountsPayableSettings.tenant_id LIMIT 1) WHERE inbox_id IS NULL');
    }
}
