<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class OauthTables extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('OAuthApplications')
            ->addColumn('name', 'string')
            ->addColumn('identifier', 'string')
            ->addColumn('secret', 'string')
            ->addColumn('redirect_uris', 'text')
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->create();

        $this->table('OAuthAuthorizationCodes')
            ->addColumn('identifier', 'string')
            ->addColumn('expires', 'timestamp')
            ->addColumn('user_identifier', 'string')
            ->addColumn('scopes', 'text')
            ->addColumn('redirect_uri', 'string')
            ->addColumn('application_id', 'integer')
            ->addForeignKey('application_id', 'OAuthApplications', 'id')
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->create();

        $this->table('OAuthAccessTokens')
            ->addColumn('identifier', 'string')
            ->addColumn('expires', 'timestamp')
            ->addColumn('user_identifier', 'string')
            ->addColumn('scopes', 'text')
            ->addColumn('application_id', 'integer')
            ->addForeignKey('application_id', 'OAuthApplications', 'id')
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->create();

        $this->table('OAuthRefreshTokens')
            ->addColumn('identifier', 'string')
            ->addColumn('expires', 'timestamp')
            ->addColumn('access_token_id', 'integer')
            ->addForeignKey('access_token_id', 'OAuthAccessTokens', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addTimestamps()
            ->addIndex('identifier', ['unique' => true])
            ->create();

        $this->table('OAuthApplicationAuthorizations')
            ->addColumn('user_id', 'integer')
            ->addColumn('tenant_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('scopes', 'text')
            ->addColumn('application_id', 'integer')
            ->addForeignKey('application_id', 'OAuthApplications', 'id')
            ->addTimestamps()
            ->create();
    }
}
