<?php

namespace App\Reports\PresetReports;

use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Reports\Enums\ColumnType;
use App\Reports\ValueObjects\FinancialReportColumn;
use App\Reports\ValueObjects\FinancialReportGroup;
use App\Reports\ValueObjects\FinancialReportRow;
use App\Reports\ValueObjects\Section;

/**
 * The reconciliation report aids in reconciling A/R numbers
 * with the books. The result output is the balance owed  from
 * customers at the end of the report period.
 */
class Reconciliation extends AbstractReport
{
    /** @var string[] */
    private array $methodNames = [];
    private Money $openingArBalance;
    private Money $openingCreditBalance;
    private Money $activity;

    public static function getId(): string
    {
        return 'reconciliation';
    }

    protected function getName(): string
    {
        return 'Reconciliation';
    }

    /**
     * Gets the A/R balance at a point in time.
     *
     * A/R balance = Invoices - Credit Notes - Invoice Payments - Bad Debt - Voids
     */
    private function getArBalance(int $timestamp): Money
    {
        return $this->getTotalInvoiced(0, $timestamp)
            ->add($this->getTotalPaid(0, $timestamp))
            ->add($this->getTotalCreditNotes(0, $timestamp))
            ->add($this->getBadDebt(0, $timestamp))
            ->add($this->getVoided(0, $timestamp));
    }

    /**
     * Gets the total invoiced during the report date range.
     */
    private function getTotalInvoiced(int $start, int $end): Money
    {
        $invoiced = (float) $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('date BETWEEN '.$start.' AND '.$end)
            ->fetchOne();

        return Money::fromDecimal($this->currency, $invoiced);
    }

    /**
     * Gets the total credit notes during the report date range.
     * This will exclude the amount converted to credit balances
     * since those are accounted for in the credit balance figures.
     */
    private function getTotalCreditNotes(int $start, int $end): Money
    {
        $creditNotes = (float) $this->database->createQueryBuilder()
            ->select('sum(cn.total)-sum(cn.amount_credited) as total')
            ->from('CreditNotes', 'cn')
            ->leftJoin('cn', 'LineItems', 'li', 'cn.id = li.credit_note_id AND li.type = "bad_debt" AND cn.paid = 1')
            ->andWhere('cn.tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('cn.currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('cn.draft = 0')
            ->andWhere('cn.date BETWEEN '.$start.' AND '.$end)
            ->andWhere('li.type IS NULL')
            ->fetchOne();

        return Money::fromDecimal($this->currency, $creditNotes)->negated();
    }

    /**
     * Gets the invoice payments during the time range.
     *
     * NOTE: this does NOT include unapplied payments
     */
    private function getTotalPaid(int $start, int $end): Money
    {
        $paid = new Money($this->currency, 0);
        foreach ($this->getPayments($start, $end) as $amount) {
            $paid = $paid->add($amount);
        }

        return $paid;
    }

    /**
     * Gets the invoice payments by payment method during the time range.
     *
     * NOTE: this does NOT include unapplied payments
     *
     * @return Money[]
     */
    private function getPayments(int $start, int $end): array
    {
        // Refunds are positive and payments are negative because it reduces the amount we can collect
        $rows = $this->database->createQueryBuilder()
            ->select('method,SUM(CASE WHEN `type`="refund" THEN amount ELSE -amount END) as amount')
            ->from('Transactions')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('status = "'.Transaction::STATUS_SUCCEEDED.'"')
            ->andWhere('type IN ("'.Transaction::TYPE_CHARGE.'", "'.Transaction::TYPE_PAYMENT.'", "'.Transaction::TYPE_REFUND.'")')
            ->andWhere('invoice IS NOT NULL')
            ->andWhere('date BETWEEN '.$start.' AND '.$end)
            ->groupBy('method')
            ->fetchAllAssociative();

        $payments = [];
        foreach ($rows as $row) {
            $payments[$row['method']] = Money::fromDecimal($this->currency, $row['amount']);
        }

        return $payments;
    }

    /**
     * Gets the invoices marked as bad debt during the report date range.
     */
    private function getBadDebt(int $start, int $end): Money
    {
        $badDebt = (float) $this->database->createQueryBuilder()
            ->select('sum(amount_written_off)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('paid = 0')
            ->andWhere('closed = 1')
            ->andWhere('date_bad_debt BETWEEN '.$start.' AND '.$end)
            ->fetchOne();

        // Negative because it reduces the amount we can collect
        return Money::fromDecimal($this->currency, $badDebt)->negated();
    }

    /**
     * Gets the previously invoices marked as bad debt that
     * were paid during the report date range.
     */
    private function getPaidBadDebt(int $start, int $end): Money
    {
        $paidBadDebt = (float) $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('paid = 1')
            ->andWhere('date_bad_debt > 0')
            ->andWhere('date_bad_debt < '.$start)
            ->andWhere('date_paid BETWEEN '.$start.' AND '.$end)
            ->fetchOne();

        return Money::fromDecimal($this->currency, $paidBadDebt);
    }

    /**
     * Gets the invoices voided during the report date range.
     * NOTE: This also includes credit notes.
     */
    private function getVoided(int $start, int $end): Money
    {
        $voidedD = (float) $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('Invoices')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 1')
            ->andWhere('date_voided BETWEEN '.$start.' AND '.$end)
            ->fetchOne();

        $voided = Money::fromDecimal($this->currency, $voidedD);

        $voidedCreditD = (float) $this->database->createQueryBuilder()
            ->select('sum(total)')
            ->from('CreditNotes')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('currency = :currency')
            ->setParameter('currency', $this->currency)
            ->andWhere('draft = 0')
            ->andWhere('voided = 1')
            ->andWhere('date_voided BETWEEN '.$start.' AND '.$end)
            ->fetchOne();

        $voidedCredit = Money::fromDecimal($this->currency, $voidedCreditD);

        // Negative because it reduces the amount we can collect
        return $voidedCredit->subtract($voided);
    }

    /**
     * Gets the total credit balances outstanding at a given point in time.
     */
    private function getCreditBalance(int $timestamp): Money
    {
        $query = 'SELECT SUM(balance2)'.
            'FROM ('.
                'SELECT ('.
                    'SELECT b1.balance '.
                    'FROM CreditBalances b1 '.
                    'WHERE b1.customer_id=b2.customer_id AND b1.`timestamp` < '.$timestamp.' '.
                    'ORDER BY b1.`timestamp` DESC,b1.transaction_id DESC '.
                    'LIMIT 1'.
                ') AS balance2 '.
                'FROM ('.
                    'SELECT b.customer_id '.
                    'FROM CreditBalances b '.
                    'JOIN Customers c ON c.id=b.customer_id '.
                    'WHERE c.tenant_id='.$this->company->id().' '.
                    'GROUP BY b.customer_id'.
                ') b2 '.
                'HAVING balance2 > 0'.
            ') b3';

        $balance = (float) $this->database->fetchOne($query);

        // Credit balances are negative because it reduces the amount we can collect
        // because this is money owed to our customers
        return Money::fromDecimal($this->currency, -$balance);
    }

    protected function build(): void
    {
        $group = new FinancialReportGroup([
            new FinancialReportColumn('', ColumnType::String),
            new FinancialReportColumn('Total', ColumnType::Money),
        ]);
        $mainRow = new FinancialReportRow();
        $mainRow->addNestedRow($this->buildOpeningSection());
        $mainRow->addNestedRow($this->buildCurrentSection());
        $mainRow->addNestedRow($this->buildClosingSection($mainRow));
        $group->addRow($mainRow);
        $section = new Section('');
        $section->addGroup($group);
        $this->report->addSection($section);
    }

    private function buildOpeningSection(): FinancialReportRow
    {
        $beforeTimestamp = $this->start - 1;

        $this->openingArBalance = $this->getArBalance($beforeTimestamp);
        $this->openingCreditBalance = $this->getCreditBalance($beforeTimestamp);
        $openingBalance = $this->openingArBalance->add($this->openingCreditBalance);

        $row = new FinancialReportRow();
        $row->setHeader('Opening balance', '');
        $row->addValue('Outstanding invoices', $this->formatMoney($this->openingArBalance));
        $row->addValue('Credit balances', $this->formatMoney($this->openingCreditBalance));
        $row->setSummary('Total opening balance', $this->formatMoney($openingBalance));

        return $row;
    }

    private function buildCurrentSection(): FinancialReportRow
    {
        $row = new FinancialReportRow();
        $row->setHeader('Transactions', '');

        // Invoices
        $invoiced = $this->getTotalInvoiced($this->start, $this->end);
        $row->addValue('Invoices generated', $this->formatMoney($invoiced));

        // Payments
        $payments = $this->getPayments($this->start, $this->end);
        $paid = new Money($this->currency, 0);

        // Applied Credit Balances
        if (isset($payments['balance'])) {
            $amount = $payments['balance'];
            if (!$amount->isZero()) {
                $row->addValue('Applied Credit Balance', $this->formatMoney($amount));
                $paid = $paid->add($amount);
            }
            unset($payments['balance']);
        }

        // All other payments
        foreach ($payments as $method => $amount) {
            if (!$amount->isZero()) {
                $name = 'Payments - '.$this->getMethodName($method);
                $row->addValue($name, $this->formatMoney($amount));
                $paid = $paid->add($amount);
            }
        }

        // Credit Notes
        $creditNotes = $this->getTotalCreditNotes($this->start, $this->end);
        if (!$creditNotes->isZero()) {
            $row->addValue('Credit notes', $this->formatMoney($creditNotes));
        }

        // Bad Debt
        $badDebt = $this->getBadDebt($this->start, $this->end);
        if (!$badDebt->isZero()) {
            $row->addValue('Invoices sent to bad debt', $this->formatMoney($badDebt));
        }

        $paidBadDebt = $this->getPaidBadDebt($this->start, $this->end);
        if (!$paidBadDebt->isZero()) {
            $row->addValue('Previous bad debt paid', $this->formatMoney($paidBadDebt));
        }

        // Voids
        $voided = $this->getVoided($this->start, $this->end);
        if (!$voided->isZero()) {
            $row->addValue('Invoices and credit notes voided', $this->formatMoney($voided));
        }

        // A/R balance = Invoices - Payments - Credit Notes - Bad Debt - Voids
        $this->activity = $invoiced->add($paid)
            ->add($creditNotes)
            ->add($badDebt)
            ->add($paidBadDebt)
            ->add($voided);

        $row->setSummary('Net transactions', $this->formatMoney($this->activity));

        return $row;
    }

    private function buildClosingSection(FinancialReportRow $mainRow): FinancialReportRow
    {
        $row = new FinancialReportRow();

        $arBalance = $this->openingArBalance->add($this->activity);
        $creditBalance = $this->getCreditBalance($this->end);
        $closingBalance = $arBalance->add($creditBalance);
        $mainRow->addValue('Net credit balance activity', $this->formatMoney($creditBalance->subtract($this->openingCreditBalance)));

        $row->setHeader('Closing balance', '');
        $row->addValue('Outstanding invoices', $this->formatMoney($arBalance));
        $row->addValue('Credit balances', $this->formatMoney($creditBalance));
        $mainRow->setSummary('Total closing balance', $this->formatMoney($closingBalance));

        return $row;
    }

    /**
     * Gets the human-readable name for a payment method.
     */
    private function getMethodName(string $id): string
    {
        if (!isset($this->methodNames[$id])) {
            $method = PaymentMethod::instance($this->company, $id);
            $this->methodNames[$id] = $method->toString();
        }

        return $this->methodNames[$id];
    }
}
