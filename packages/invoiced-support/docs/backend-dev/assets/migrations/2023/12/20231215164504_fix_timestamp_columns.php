<?php

declare(strict_types=1);

use App\Core\Multitenant\MultitenantModelMigration;

final class FixTimestampColumns extends MultitenantModelMigration
{
    public function change(): void
    {
        $this->table('OAuthAuthorizationCodes')
            ->changeColumn('expires', 'timestamp', ['default' => 0])
            ->update();
        $this->table('OAuthAccessTokens')
            ->changeColumn('expires', 'timestamp', ['default' => 0])
            ->update();
        $this->table('OAuthRefreshTokens')
            ->changeColumn('expires', 'timestamp', ['default' => 0])
            ->update();
        $this->table('MarketingAttributions')
            ->changeColumn('timestamp', 'timestamp', ['default' => 0])
            ->update();
        $this->table('CustomerPortalEvents')
            ->changeColumn('timestamp', 'timestamp', ['default' => 0])
            ->update();
        $this->table('InstalledProducts')
            ->changeColumn('installed_on', 'timestamp', ['default' => 0])
            ->update();
        $this->table('OAuthAccounts')
            ->changeColumn('access_token_expiration', 'timestamp', ['default' => 0])
            ->update();
        $this->table('NetworkInvitations')
            ->changeColumn('expires_at', 'timestamp', ['default' => 0])
            ->update();
        $this->table('ScheduledReports')
            ->changeColumn('next_run', 'timestamp', ['default' => 0])
            ->update();
    }
}
