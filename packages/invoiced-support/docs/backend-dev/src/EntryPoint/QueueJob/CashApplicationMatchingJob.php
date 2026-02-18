<?php

namespace App\EntryPoint\QueueJob;

use App\CashApplication\Libs\CashApplicationMatchmaker;
use App\CashApplication\Models\Payment;
use App\Core\Multitenant\Interfaces\TenantAwareQueueJobInterface;
use App\Core\Queue\AbstractResqueJob;
use App\Core\Queue\Interfaces\MaxConcurrencyInterface;

class CashApplicationMatchingJob extends AbstractResqueJob implements TenantAwareQueueJobInterface, MaxConcurrencyInterface
{
    public function __construct(private CashApplicationMatchmaker $matchmaker)
    {
    }

    public function perform(): void
    {
        if ($payment = Payment::find($this->args['payment'])) {
            $this->matchmaker->run($payment, $this->args['isEdit']);
        }
    }

    public static function getMaxConcurrency(array $args): int
    {
        // Only 1 payment per account can be matched to invoices at a time.
        return 1;
    }

    public static function getConcurrencyKey(array $args): string
    {
        return 'cash_match:'.$args['tenant_id'];
    }

    public static function getConcurrencyTtl(array $args): int
    {
        return 300; // 5 minutes
    }

    public static function delayAtConcurrencyLimit(): bool
    {
        return true;
    }
}
