<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class OAuthAccount extends MultitenantModelMigration
{
    public function change(): void
    {
        $table = $this->table('OAuthAccounts');
        $this->addTenant($table);
        $table->addColumn('integration', 'tinyinteger')
            ->addColumn('name', 'string')
            ->addColumn('access_token', 'text')
            ->addColumn('access_token_expiration', 'timestamp')
            ->addColumn('refresh_token', 'text')
            ->addColumn('refresh_token_expiration', 'timestamp', ['null' => true, 'default' => null])
            ->addColumn('metadata', 'json', ['null' => true, 'default' => null])
            ->addTimestamps()
            ->create();
    }
}
