<?php

namespace App\Reports\PresetReports;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\ReportBuilder;
use App\Reports\ValueObjects\ChartGroup;
use App\Reports\ValueObjects\Report;
use App\Reports\ValueObjects\Section;
use Doctrine\DBAL\Connection;

class ChasingActivity extends AbstractReportBuilderReport
{
    public function __construct(
        ReportBuilder $reportBuilder,
        private Connection $database,
    ) {
        parent::__construct($reportBuilder);
    }

    public static function getId(): string
    {
        return 'chasing_activity';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('chasing_activity.json');
    }

    public function generate(Company $company, array $parameters): Report
    {
        $report = parent::generate($company, $parameters);

        $this->addChasingCustomers($report);
        $this->addChasingInvoices($report);

        return $report;
    }

    private function addChasingCustomers(Report $report): void
    {
        $sql = "SELECT count(*) as total, sum(c.chasing_cadence_id IS NOT NULL) as chased
        FROM Customers c
        WHERE id IN (SELECT customer
            FROM Invoices
            WHERE balance > 0
                AND tenant_id = :tenantId
                AND status IN ('past_due', 'sent', 'not_sent', 'viewed')
        )";

        $qb = $this->database->prepare($sql);
        $qb->bindValue('tenantId', $this->company->id());

        $counters = $qb->executeQuery()->fetchAssociative();
        if (!$counters || !$counters['total']) {
            return;
        }
        $percentCustomerChasing = new ChartGroup();
        $percentCustomerChasing->setChartType('pie');
        $applied = $counters['chased'];
        $unApplied = $counters['total'] - $counters['chased'];
        $percentCustomerChasing->setData([
            'datasets' => [
                [
                    'data' => [
                        $unApplied,
                        $applied,
                    ],
                    'backgroundColor' => [
                        '#10806F',
                        '#B4D9BD',
                    ],
                ],
            ],
            'labels' => [
                'Customers Without Chasing ('.round($unApplied / $counters['total'] * 100, 2).'%)',
                'Customers With Chasing ('.round($applied / $counters['total'] * 100, 2).'%)',
            ],
        ]);

        $section = new Section('Open Customers With Chasing Enabled');
        $section->addGroup($percentCustomerChasing);
        $report->addSection($section);
    }

    private function addChasingInvoices(Report $report): void
    {
        $qb = $this->database->createQueryBuilder()
            ->select('COUNT(*) as total')
            ->from('Invoices', 'i')
            ->join('i', 'Customers', 'c', 'i.customer = c.id')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.balance > 0')
            ->andWhere('c.chasing_cadence_id IS NULL');

        $qb->andWhere($qb->expr()->in('status', ':statuses'))
            ->setParameter('statuses', ['past_due', 'sent', 'not_sent', 'viewed'], Connection::PARAM_STR_ARRAY);

        $total = (int) $qb->fetchOne();
        if (!$total) {
            return;
        }

        $qb = $this->database->createQueryBuilder()
            ->select('COUNT(*) as total')
            ->from('InvoiceDeliveries', 'i')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.disabled = 0');

        $chased = (int) $qb->fetchOne();

        $percentCustomerChasing = new ChartGroup();
        $percentCustomerChasing->setChartType('pie');
        $unApplied = $total - $chased;
        $percentCustomerChasing->setData([
            'datasets' => [
                [
                    'data' => [
                        $unApplied,
                        $chased,
                    ],
                    'backgroundColor' => [
                        '#10806F',
                        '#B4D9BD',
                    ],
                ],
            ],
            'labels' => [
                'Invoices Without Chasing ('.round($unApplied / $total * 100, 2).'%)',
                'Invoices With Chasing ('.round($chased / $total * 100, 2).'%)',
            ],
        ]);

        $section = new Section('Open Invoices With Chasing Enabled');
        $section->addGroup($percentCustomerChasing);
        $report->addSection($section);
    }
}
