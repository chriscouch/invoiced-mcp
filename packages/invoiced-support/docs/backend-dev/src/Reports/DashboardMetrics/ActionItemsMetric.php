<?php

namespace App\Reports\DashboardMetrics;

use App\CashApplication\Enums\RemittanceAdviceStatus;
use App\CashApplication\Models\RemittanceAdvice;
use App\Companies\Models\Member;
use App\Integrations\Enums\IntegrationType;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentProcessing\Enums\DisputeStatus;
use App\Reports\Libs\ReportHelper;
use App\Reports\ValueObjects\DashboardContext;
use App\Sending\Email\Models\EmailThread;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use Throwable;

class ActionItemsMetric extends AbstractDashboardMetric
{
    public static function getName(): string
    {
        return 'action_items';
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
        return CarbonImmutable::now()->addDay();
    }

    public function build(DashboardContext $context, array $options): array
    {
        $this->setContext($context);

        $accountingSystem = null;
        $totalReconciliationErrors = 0;
        foreach ($this->getReconciliationErrorsByIntegration() as $row) {
            $totalReconciliationErrors += $row['num_errors'];
            if (!$accountingSystem) {
                $accountingSystem = IntegrationType::from($row['integration_id'])->toString();
            }
        }

        $result = [
            'num_autopay_invoices_missing_payment_info' => $this->getTotalAutoPayInvoicesMissingPaymentInfo(),
            'num_broken_promises' => $this->getTotalBrokenPromises(),
            'num_my_todo' => $this->getTotalMyDueTasks(),
            'num_needs_attention' => $this->getTotalNeedsAttention(),
            'num_open_disputes' => $this->getTotalOpenDisputes(),
            'num_open_email_threads' => $this->getTotalOpenEmailThreads(),
            'num_reconciliation_errors' => $totalReconciliationErrors,
            'num_remittance_advice_exceptions' => $this->getRemittanceAdviceExceptions(),
            'num_unapplied_payments' => $this->getTotalUnappliedPayments(),
            'num_unapproved_payment_plans' => $this->getTotalUnapprovedPaymentPlans(),
        ];
        $result['count'] = array_reduce($result, fn (int $i, int $j) => $i + $j, 0);
        $result['accounting_system'] = $accountingSystem;

        return $result;
    }

    /**
     * Gets the # of invoices that were not paid by the expected payment date.
     */
    public function getTotalBrokenPromises(): int
    {
        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Invoices', 'i')
            ->join('i', 'ExpectedPaymentDates', 'e', 'invoice_id=i.id')
            ->andWhere('e.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.paid = 0')
            ->andWhere('i.draft = 0')
            ->andWhere('i.closed = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('e.date < '.time());

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('i.customer')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        return $query->fetchOne();
    }

    /**
     * Gets the # of invoices that need attention.
     */
    public function getTotalNeedsAttention(): int
    {
        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('paid = 0')
            ->andWhere('draft = 0')
            ->andWhere('closed = 0')
            ->andWhere('voided = 0')
            ->andWhere('needs_attention = 1');

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Invoices.customer')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        return $query->fetchOne();
    }

    /**
     * Generates the # of to do items assigned to the current
     * user that are due today.
     */
    public function getTotalMyDueTasks(): int
    {
        if (!$this->member) {
            return 0;
        }

        $endOfToday = mktime(23, 59, 59);

        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Tasks')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('complete = 0')
            ->andWhere('user_id = :userId')
            ->setParameter('userId', $this->member->user_id)
            ->andWhere('due_date <= '.$endOfToday);

        // Limit the result set for the member's customer restrictions.
        if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
            if ($restriction = $this->restrictionQueryBuilder->buildSql('Tasks.customer_id')) {
                $query->andWhere($restriction);
            }
        } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
            $query->andWhere('customer_id IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
        }

        return (int) $query->fetchOne();
    }

    /**
     * Gets the # of invoices that have an unapproved payment plan.
     */
    public function getTotalUnapprovedPaymentPlans(): int
    {
        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('PaymentPlans', 'p')
            ->join('p', 'Invoices', 'i', 'i.payment_plan_id=p.id')
            ->andWhere('p.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.paid = 0')
            ->andWhere('i.draft = 0')
            ->andWhere('i.closed = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('p.status = "'.PaymentPlan::STATUS_PENDING_SIGNUP.'"');

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('i.customer')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        return $query->fetchOne();
    }

    /**
     * Gets the # of AutoPay invoices for customers
     * without payment information.
     */
    public function getTotalAutoPayInvoicesMissingPaymentInfo(): int
    {
        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Customers', 'c')
            ->join('c', 'Invoices', 'i', 'customer=c.id')
            ->andWhere('c.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('i.autopay = 1')
            ->andWhere('i.paid = 0')
            ->andWhere('i.draft = 0')
            ->andWhere('i.closed = 0')
            ->andWhere('i.voided = 0')
            ->andWhere('default_source_id IS NULL');

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('c.id')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('c.id IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        return $query->fetchOne();
    }

    public function getTotalUnappliedPayments(): int
    {
        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Payments')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('voided = 0')
            ->andWhere('applied = 0');

        // Limit the result set for the member's customer restrictions.
        if ($this->member) {
            if (Member::CUSTOM_FIELD_RESTRICTION == $this->member->restriction_mode) {
                if ($restriction = $this->restrictionQueryBuilder->buildSql('Payments.customer')) {
                    $query->andWhere($restriction);
                }
            } elseif (Member::OWNER_RESTRICTION == $this->member->restriction_mode) {
                $query->andWhere('customer IN (SELECT id FROM Customers WHERE tenant_id='.$this->company->id().' AND owner_id='.$this->member->user_id.')');
            }
        }

        return $query->fetchOne();
    }

    /**
     * Generates the # of Open Email Threads.
     */
    public function getTotalOpenEmailThreads(): int
    {
        if (!$this->company->features->has('inboxes')) {
            return 0;
        }

        return EmailThread::where('status', 'open')->count();
    }

    /**
     * Generates the # of remittance advice with exceptions.
     */
    public function getRemittanceAdviceExceptions(): int
    {
        if (!$this->company->features->has('cash_application')) {
            return 0;
        }

        try {
            return RemittanceAdvice::where('status', RemittanceAdviceStatus::Exception->value)->count();
        } catch (Throwable) {
            // TODO: can remove this later
            return 0;
        }
    }

    /**
     * Generates the # of reconciliation errors.
     */
    public function getReconciliationErrorsByIntegration(): array
    {
        if (!$this->company->features->has('accounting_sync')) {
            return [];
        }

        if ($this->member && !$this->member->allowed('settings.edit')) {
            return [];
        }

        return $this->database->fetchAllAssociative(
            'SELECT integration_id,COUNT(*) as num_errors FROM ReconciliationErrors WHERE tenant_id=:tenantId GROUP BY integration_id ORDER BY num_errors DESC',
            [
                'tenantId' => $this->company->id,
            ]
        );
    }

    /**
     * Gets the # of invoices that were not paid by the expected payment date.
     */
    public function getTotalOpenDisputes(): int
    {
        if (!$this->company->features->has('flywire_invoiced_payments')) {
            return 0;
        }

        $query = $this->database->createQueryBuilder()
            ->select('count(*)')
            ->from('Disputes', 'd')
            ->andWhere('d.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('d.status NOT IN ('.implode(',', [DisputeStatus::Expired->value, DisputeStatus::Accepted->value, DisputeStatus::Lost->value, DisputeStatus::Won->value]).')');

        return $query->fetchOne();
    }
}
