<?php

namespace App\EntryPoint\CronJob;

use App\Core\Multitenant\TenantContext;
use App\Integrations\Exceptions\OAuthException;
use App\Integrations\OAuth\OAuthConnectionManager;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksOAuth;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Core\Orm\Query;

/**
 * Refreshes all expired (or soon expiring) QBO access tokens.
 */
class RefreshQuickBooksAccessTokens extends AbstractTaskQueueCronJob
{
    const BATCH_SIZE = 250;

    private int $count;

    public function __construct(
        private TenantContext $tenant,
        private OAuthConnectionManager $oauthManager,
        private QuickBooksOAuth $oauth,
    ) {
    }

    public static function getName(): string
    {
        return 'refresh_qbo_tokens';
    }

    public static function getLockTtl(): int
    {
        return 900;
    }

    public function getTasks(): iterable
    {
        $query = $this->getQuickBooksAccounts();
        $this->count = $query->count();

        return $query->first(self::BATCH_SIZE);
    }

    public function getTaskCount(): int
    {
        return $this->count;
    }

    /**
     * @param QuickBooksAccount $task
     */
    public function runTask(mixed $task): bool
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($task->tenant());

        try {
            $this->oauthManager->refresh($this->oauth, $task);
            $refreshed = true;
        } catch (OAuthException) {
            // do nothing, move on to the next account
            // because any error has already been logged
            $refreshed = false;
        }

        // IMPORTANT: clear the current tenant after we are done
        $this->tenant->clear();

        return $refreshed;
    }

    /**
     * Gets the QBO accounts that are expiring within 30 days or
     * have already expired.
     */
    public function getQuickBooksAccounts(): Query
    {
        $expiresSoon = strtotime('+30 days');

        return QuickBooksAccount::queryWithoutMultitenancyUnsafe()
            ->where('refresh_token_expires', $expiresSoon, '<=');
    }
}
