<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class BackfillVerifiedEmails extends MultitenantModelMigration
{
    public function up(): void
    {
        $this->execute('INSERT IGNORE INTO CompanyEmailAddresses (tenant_id, email, token, code, verified_at, created_at) SELECT id AS tenant_id, email, LPAD(id, 24, 0) AS token, 674802 AS code, NOW() as verified_at, NOW() AS created_at FROM Companies WHERE verified_email=1');
    }
}
