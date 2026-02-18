<?php

namespace App\EntryPoint\QueueJob;

use App\Companies\Libs\CompanyReset;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * This class is used to test the max concurrency feature.
 */
class CompanyResetJob extends AbstractResqueJob implements LoggerAwareInterface, MaxConcurrencyInterface, TenantAwareQueueJobInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly CompanyReset $companyReset,
        private readonly TenantContext $tenantContext
    ) {
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'company_reset';
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 1;
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 86400;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return false;
    }

    public function perform(): void
    {
        $company = $this->tenantContext->get();
        $this->companyReset->clearData($company);

        if (isset($this->args['settings']) && $this->args['settings']) {
            $this->companyReset->clearSettings($company);
        }
    }
}
