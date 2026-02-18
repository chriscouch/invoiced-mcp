<?php

namespace App\Reports\DashboardMetrics;

use App\AccountsPayable\Enums\ApAccounts;
use App\AccountsPayable\Ledger\AccountsPayableLedger;
use App\AccountsPayable\Models\Vendor;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Ledger\Ledger;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;

class TopVendorsMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'top_vendors';
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

        $n = $options['count'] ?? 5;
        $ledger = $this->accountsPayableLedger->getLedger($context->company);

        return [
            'currency' => $context->company->currency,
            'top_vendors' => $this->getTopVendors($ledger, $n),
        ];
    }

    private function getTopVendors(Ledger $ledger, int $count): array
    {
        $date = CarbonImmutable::now();
        $balances = $ledger->reporting->getPartyBalances(ApAccounts::Purchases->value, $count, $date);

        $result = [];
        foreach ($balances as $balance) {
            $result[] = [
                'id' => $balance['party_id'],
                'name' => Vendor::find($balance['party_id'])?->name,
                'total_spend' => Money::fromMoneyPhp($balance['balance'])->toDecimal(),
            ];
        }

        return $result;
    }
}
