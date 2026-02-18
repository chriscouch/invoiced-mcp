<?php

namespace App\Sending\Email\Adapter;

use App\Companies\Models\Company;
use App\Core\Entitlements\Enums\QuotaType;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Sending\Email\Exceptions\EmailLimitException;
use App\Sending\Email\Exceptions\SendEmailException;
use App\Sending\Email\Interfaces\AdapterInterface;
use App\Sending\Email\Interfaces\EmailInterface;
use DateInterval;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\Policy\FixedWindowLimiter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Sends in sequential order through a given list of adapters until the first one succeeds.
 */
class FailoverAdapter implements AdapterInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;

    /**
     * @param AdapterInterface[] $adapters
     */
    public function __construct(
        private array $adapters,
        private CacheStorage $storage,
        private LockFactory $lockFactory,
    ) {
        if (!count($this->adapters)) {
            throw new RuntimeException('Must supply at least one adapter');
        }
    }

    public function isInvoicedService(): bool
    {
        return false;
    }

    public function send(EmailInterface $message): void
    {
        $checkedLimit = false;
        $exception = null;
        foreach ($this->adapters as $adapter) {
            // Check if the user has exceeded their rate limit only if this adapter
            // is using an Invoiced own email service.
            if (!$checkedLimit && $adapter->isInvoicedService()) {
                $checkedLimit = true;
                $this->checkLimit($message);
            }

            try {
                $adapter->send($message);

                // If no exception then we can return with the first succeeded adapter
                return;
            } catch (SendEmailException $e) {
                // Do not throw the exception yet because we will try the last adapter.
                // The final exception is what will be thrown.
                $exception = $e;
            }
        }

        if ($exception) {
            throw $exception;
        }
    }

    /**
     * @throws SendEmailException
     */
    private function checkLimit(EmailInterface $message): void
    {
        $company = $message->getCompany();
        $limiter = $this->getLimiter($company);
        if ($limiter && !$limiter->consume()->isAccepted()) {
            $this->statsd->increment('email.daily_limit', 1.0, ['tenant_id' => $company->id]);

            throw new EmailLimitException('You have exceeded your daily send limit. Please try again later.');
        }
    }

    public function getAdapters(): array
    {
        return $this->adapters;
    }

    private function getLimiter(Company $company): ?LimiterInterface
    {
        $limit = $company->quota->get(QuotaType::CustomerEmailDailyLimit);
        if (!$limit) {
            return null;
        }

        // The ID has to include the limit because if the limit
        // changes then it will still use the previous limit.
        $id = $company->id.'-'.$limit;
        $interval = new DateInterval('P1D');
        $lock = $this->lockFactory->createLock("customer_email_limiter-$id-$limit");

        return new FixedWindowLimiter($id, $limit, $interval, $this->storage, $lock);
    }
}
