<?php

namespace App\EntryPoint\CronJob;

use App\Companies\Models\Company;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Exceptions\IntegrationApiException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * This cron job creates allowed origins on our Adyen
 * client key to permit the Drop-In components to be
 * loaded on custom domains.
 */
class AdyenCustomDomains extends AbstractTaskQueueCronJob implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(private AdyenClient $adyen)
    {
    }

    public static function getLockTtl(): int
    {
        return 1800;
    }

    public function getTasks(): iterable
    {
        return Company::where('custom_domain', null, '<>')
            ->all();
    }

    /**
     * @param Company $task
     */
    public function runTask(mixed $task): bool
    {
        try {
            $this->adyen->createAllowedOrigin($task->url);
        } catch (IntegrationApiException $e) {
            $response = $e->getResponse();
            $body = (string) $response?->getContent(false);
            if (!str_contains($body, 'Origin already exists')) {
                $this->logger->error('Could not add custom domain to Adyen', ['exception' => $e]);
            }

            return false;
        }

        return true;
    }
}
