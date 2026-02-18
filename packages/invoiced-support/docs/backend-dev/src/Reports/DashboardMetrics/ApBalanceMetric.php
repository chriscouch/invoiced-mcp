<?php

namespace App\Reports\DashboardMetrics;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;

class ApBalanceMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'ap_balance';
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

        $totalBalance = Money::fromMoneyPhp($ledger->reporting->getAccountBalance(ApAccounts::AccountsPayable->value, $date));

        return [
            'currency' => $totalBalance->currency,
            'total_balance' => $totalBalance->negated()->toDecimal(),
        ];
    }
}
