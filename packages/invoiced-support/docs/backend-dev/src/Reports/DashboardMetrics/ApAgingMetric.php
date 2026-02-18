<?php

namespace App\Reports\DashboardMetrics;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\AgingBreakdown;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;

class ApAgingMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'ap_aging';
    }

    public function __construct(private AccountsPayableLedger $accountsPayableLedger, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return true;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->endOfDay();
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);

        $date = CarbonImmutable::now();
        $ledger = $this->accountsPayableLedger->getLedger($context->company);
        $agingBreakdown = AgingBreakdown::fromSettings($this->company->accounts_payable_settings);

        $agingData = $ledger->reporting->getAging($agingBreakdown, ApAccounts::AccountsPayable->value, $date);

        foreach ($agingData as &$agingDatum) {
            // A/P balances are negative but we want to display as positive
            $agingDatum['amount'] = Money::fromMoneyPhp($agingDatum['amount'])->negated()->toDecimal();
        }

        $agingDate = $agingBreakdown->dateColumn;

        return [
            'currency' => $context->company->currency,
            'aging' => $agingData,
            'aging_date' => $agingDate,
        ];
    }
}
