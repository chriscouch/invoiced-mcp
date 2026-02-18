<?php

namespace App\EntryPoint\QueueJob;

use App\CashApplication\Models\CashApplicationBankAccount;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Integrations\Plaid\Libs\PlaidTransactionProcessor;
use Carbon\CarbonImmutable;

class PlaidTransactionJob extends AbstractResqueJob implements TenantAwareQueueJobInterface
{
    public function __construct(private PlaidTransactionProcessor $processor)
    {
    }

    public function perform(): void
    {
        $bankAccount = CashApplicationBankAccount::findOrFail($this->args['bank_account']);
        $startDate = new CarbonImmutable($this->args['start_date']);
        $endDate = new CarbonImmutable($this->args['end_date']);
        $this->processor->process($bankAccount, $startDate, $endDate);
    }
}
