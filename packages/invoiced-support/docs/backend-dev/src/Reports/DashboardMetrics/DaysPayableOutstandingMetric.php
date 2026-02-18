<?php

namespace App\Reports\DashboardMetrics;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Ledger;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;

class DaysPayableOutstandingMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'days_payable_outstanding';
    }

    public function __construct(private AccountsPayableLedger $accountsPayableLedger, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);

        $date = CarbonImmutable::now();
        $ledger = $this->accountsPayableLedger->getLedger($context->company);

        return [
            'dpo' => $this->daysPayableOutstanding($context, $ledger, $date),
        ];
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return false;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->endOfDay();
    }

    /**
     * Gets the Days Payable Outstanding (DSO) metric.
     */
    private function daysPayableOutstanding(DashboardContext $context, Ledger $ledger, CarbonImmutable $date): int
    {
        // get the total amount purchased over the period
        // If an account has < 365 days of history then use
        // the company creation date to determine how many days of purchases we have
        $startDate = $context->company->created_at;
        $daysSinceStart = max(1, ceil((time() - $startDate) / 86400));
        $daysInPeriod = (int) min(365, $daysSinceStart);

        $totalPurchases = Money::fromMoneyPhp($ledger->reporting->getAccountBalance(ApAccounts::Purchases->value, $date));

        // if there are no purchases then this metric is not available
        if ($totalPurchases->isZero()) {
            return -1;
        }

        $currentBalance = Money::fromMoneyPhp($ledger->reporting->getAccountBalance(ApAccounts::AccountsPayable->value, $date));

        // DPO = Accounts Payable / Total Purchases * Days In Period
        return (int) round($currentBalance->negated()->toDecimal() / $totalPurchases->toDecimal() * $daysInPeriod);
    }
}
