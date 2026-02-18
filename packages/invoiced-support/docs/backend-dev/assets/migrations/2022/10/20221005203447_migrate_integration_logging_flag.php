<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class MigrateIntegrationLoggingFlag extends MultitenantModelMigration
{
    private const NEW_FLAGS = [
        'log_avalara',
        'log_intacct',
        'log_netsuite',
        'log_plaid',
        'log_quickbooks_desktop',
        'log_quickbooks',
        'log_smtp',
        'log_slack',
        'log_xero',
    ];

    public function change(): void
    {
        foreach (self::NEW_FLAGS as $flag) {
            $this->execute('INSERT INTO Features (tenant_id, feature, enabled) SELECT tenant_id, "'.$flag.'", 1 FROM Features JOIN Companies C ON C.id=tenant_id WHERE feature="integration_logging" AND enabled=1 AND canceled=0');
        }

        $this->execute('DELETE FROM Features WHERE feature="integration_logging"');
    }
}
