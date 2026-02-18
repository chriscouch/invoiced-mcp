<?php

namespace App\Reports\DashboardMetrics;

use App\Core\I18n\ValueObjects\Money;
use App\Network\Enums\DocumentStatus;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;

class BillsByStatusMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'bills_by_status';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
    {
        parent::__construct($helper);
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);
        $currency = $options['currency'] ?? $context->company->currency;

        $data = $this->database->fetchAllAssociative('SELECT current_status,SUM(total) AS total,COUNT(*) AS n FROM NetworkDocuments WHERE to_company_id=:tenantId AND currency=:currency GROUP BY current_status', [
            'tenantId' => $context->company->id,
            'currency' => $currency,
        ]);

        $statusTotals = [];
        foreach (DocumentStatus::cases() as $status) {
            $statusTotals[$status->name] = [
                'count' => 0,
                'amount' => 0,
            ];
        }

        foreach ($data as $row) {
            $status = DocumentStatus::from($row['current_status']);
            $statusTotals[$status->name] = [
                'amount' => Money::fromDecimal($currency, $row['total'])->toDecimal(),
                'count' => $row['n'],
            ];
        }

        return [
            'currency' => $currency,
            'by_status' => $statusTotals,
        ];
    }

    public function invalidateCacheAfterEvent(): bool
    {
        return true;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addWeek();
    }
}
