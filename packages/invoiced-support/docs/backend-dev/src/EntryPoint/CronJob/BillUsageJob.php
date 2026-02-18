<?php

namespace App\EntryPoint\CronJob;

use App\Core\Billing\Action\BillOverageAction;
use App\Core\Billing\Usage\OverageChargeGenerator;
use App\Core\Billing\ValueObjects\MonthBillingPeriod;
use App\Core\Cron\Interfaces\CronJobInterface;
use App\Core\Cron\ValueObjects\Run;

/**
 * Recurring job to bill for last month's usage.
 */
class BillUsageJob implements CronJobInterface
{
    public function __construct(
        private OverageChargeGenerator $usageCalculator,
        private BillOverageAction $billOverageAction
    ) {
    }

    public static function getName(): string
    {
        return 'bill_usage';
    }

    public static function getLockTtl(): int
    {
        return 3600;
    }

    public function execute(Run $run): void
    {
        // build the overage charges for last month
        $period = MonthBillingPeriod::fromTimestamp((int) strtotime('-1 month'));
        $charges = $this->usageCalculator->generateAllOverages($period);

        // bill them out
        $n = 0;
        foreach ($charges as $charge) {
            if ($this->billOverageAction->billCharge($charge)) {
                ++$n;
            }
        }

        $run->writeOutput("Billed $n companies for usage overages in {$period->getName()}");
    }
}
