<?php

namespace App\Integrations\Adyen\ReportHandler;

use App\Integrations\Adyen\Enums\ReportType;
use App\Integrations\Adyen\Interfaces\ReportHandlerInterface;

class ReportHandlerFactory
{
    public function __construct(
        private AccountingReportHandler $accountingReportHandler,
        private BalanceReportHandler $balanceReportHandler,
        private PayoutReportHandler $payoutHandler,
    ) {
    }

    public function get(ReportType $reportType): ?ReportHandlerInterface
    {
        return match ($reportType) {
            ReportType::BalancePlatformAccounting => $this->accountingReportHandler,
            ReportType::BalancePlatformBalance => $this->balanceReportHandler,
            ReportType::BalancePlatformPayout => $this->payoutHandler,
            default => null,
        };
    }
}
