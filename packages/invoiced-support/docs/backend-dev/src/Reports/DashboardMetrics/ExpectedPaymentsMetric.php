<?php

namespace App\Reports\DashboardMetrics;

use App\Companies\Models\Member;
use App\Core\I18n\ValueObjects\Money;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class ExpectedPaymentsMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'expected_payments';
    }

    public function __construct(private Connection $database, ReportHelper $helper)
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
        $currency = $options['currency'] ?? $context->company->currency;

        $total = $this->getInvoicePromiseToPays($currency)
            ->add($this->getScheduledAutoPays($currency));

        return [
            'currency' => $total->currency,
            'total' => $total->toDecimal(),
        ];
    }

    private function getInvoicePromiseToPays(string $currency): Money
    {
        $total = $this->startInvoiceQuery($currency)
            ->join('i', 'ExpectedPaymentDates', 'e', 'i.id=e.invoice_id')
            ->andWhere('e.date > '.time())
            ->fetchOne();

        return Money::fromDecimal($currency, $total ?? 0);
    }

    private function getScheduledAutoPays(string $currency): Money
    {
        $total = $this->startInvoiceQuery($currency)
            ->andWhere('i.autopay = 1')
            ->andWhere('i.next_payment_attempt IS NOT NULL')
            ->fetchOne();

        return Money::fromDecimal($currency, $total ?? 0);
    }

    private function startInvoiceQuery(string $currency): QueryBuilder
    {
        $query = $this->database->createQueryBuilder()
            ->select('sum(i.balance)')
            ->from('Invoices', 'i')
            ->join('i', 'Customers', 'c', 'i.customer=c.id')
            ->andWhere('i.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.currency = :currency')
            ->setParameter('currency', $currency)
            ->andWhere('i.draft = 0')
            ->andWhere('i.paid = 0')
            ->andWhere('i.closed = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('i.date <= :date')
            ->setParameter('date', time());

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('c.id')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('c.owner_id = :ownerId')
                    ->setParameter('ownerId', $this->member->user_id);
            }
        }

        if ($this->customer) {
            $query->andWhere('customer = :customer')
                ->setParameter('customer', $this->customer->id());
        }

        return $query;
    }
}
