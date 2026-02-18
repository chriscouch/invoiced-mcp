<?php

namespace App\Statements\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;

/**
 * Generates a balance forward statement.
 */
final class BalanceForwardStatement extends AbstractStatement
{
    /** @var BalanceForwardStatementLineInterface[] */
    private array $lines;

    public function __construct(
        private BalanceForwardStatementData $data,
        Customer $customer,
        ?string $currency = null,
        ?int $start = null,
        ?int $end = null,
    ) {
        parent::__construct($customer, $currency);

        // When no start date is given the start date
        // is the first of the current month.
        $this->start = $start ?? (int) mktime(0, 0, 0, (int) date('m'), 1, (int) date('Y'));
        // When no end date is given the end date
        // is the current point in time.
        $this->end = $end ?? time();
        $this->type = 'balance_forward';
    }

    protected function calculatePrimaryCurrency(): string
    {
        return $this->customer->calculatePrimaryCurrency($this->start, $this->end);
    }

    /**
     * Sets the statement lines for generating sample statements.
     *
     * @param BalanceForwardStatementLineInterface[] $lines
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    /**
     * Gets a sorted list of statement lines based on
     * transactions in the customer's account.
     *
     * @return BalanceForwardStatementLineInterface[]
     */
    private function getLines(array $customerIds): array
    {
        if (isset($this->lines)) {
            return $this->lines;
        }

        return $this->data->getLines($this->getPreviousStatement(), $customerIds, $this->getCurrency(), (int) $this->start, $this->end);
    }

    /**
     * Gets the previous statement before this start date.
     */
    public function getPreviousStatement(): ?self
    {
        if (!$this->start) {
            return null;
        }

        return new self($this->data, $this->customer, $this->currency, 0, $this->start - 1);
    }

    protected function calculate(): array
    {
        $customerIds = $this->data->getCustomerIds($this->customer);
        $activity = $this->getLines($customerIds);

        $totals = new BalanceForwardStatementTotals($this->getCurrency());
        foreach ($activity as $item) {
            $item->apply($totals);
        }

        return [
            // customer IDs
            'customerIds' => $customerIds,
            // account balance
            'previousBalance' => $totals->getPreviousBalance()->toDecimal(),
            'totalInvoiced' => $totals->getTotalInvoiced()->toDecimal(),
            'totalPaid' => $totals->getTotalPaid()->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
            'accountDetail' => $totals->getAccountDetail(),
            'unifiedDetail' => $totals->getUnifiedDetail(),
            'totalUnapplied' => 0,
            'aging' => $this->buildAging(),
            // credits
            'previousCreditBalance' => $totals->getPreviousCreditBalance()->toDecimal(),
            'totalCreditsIssued' => $totals->getTotalCreditsIssued()->toDecimal(),
            'totalCreditsSpent' => $totals->getTotalCreditsSpent()->toDecimal(),
            'creditDetail' => $totals->getCreditDetail(),
            'creditBalance' => $totals->getRunningCreditBalance()->toDecimal(),
        ];
    }
}
