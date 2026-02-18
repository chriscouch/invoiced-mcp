<?php

namespace App\EntryPoint\QueueJob;

use App\Companies\Models\Company;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;
use App\Integrations\Adyen\Operations\RunAdyenTopUpProcedure;
use App\PaymentProcessing\Models\MerchantAccount;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class AdyenTopUpJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly RunAdyenTopUpProcedure $runAdyenTopUpProcedure
    ) {
    }


    public function perform(): void
    {
        $company = Company::find($this->args['companyId']);
        if (!$company) {
            return;
        }

        $this->tenant->set($company);

        $merchantAccount = MerchantAccount::find($this->args['merchantAccountId']);
        if (!$merchantAccount) {
            return;
        }

        $this->runAdyenTopUpProcedure->perform($merchantAccount, $company, dryRun: false);
    }

    public static function getMaxConcurrency(array $args): int
    {
        return 5;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'adyen_top_up:' . $args['merchantAccountId'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 1800;
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return false;
    }
}