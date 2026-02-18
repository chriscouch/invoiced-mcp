<?php

namespace App\Statements\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\OpenItemStatementLineInterface;

/**
 * Generates an Open Item statement.
 */
final class OpenItemStatement extends AbstractStatement
{
    /** @var OpenItemStatementLineInterface[] */
    private array $lines;

    public function __construct(
        private OpenItemStatementData $data,
        Customer $customer,
        ?string $currency = null,
        ?int $date = null,
        bool $pastDueOnly = false,
    ) {
        parent::__construct($customer, $currency);

        // NOTE: the end is the statement date
        // there is no start date
        $this->end = $date ?? time();
        $this->pastDueOnly = $pastDueOnly;
        $this->type = 'open_item';
    }

    protected function calculatePrimaryCurrency(): string
    {
        return $this->customer->calculatePrimaryCurrency(null, $this->end, true);
    }

    /**
     * Sets the statement lines for generating sample statements.
     *
     * @param OpenItemStatementLineInterface[] $lines
     */
    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    /**
     * Gets a sorted list of statement lines based on
     * transactions in the customer's account.
     *
     * @return OpenItemStatementLineInterface[]
     */
    private function getLines(array $customerIds): array
    {
        if (isset($this->lines)) {
            return $this->lines;
        }

        return $this->data->getLines($customerIds, $this->getCurrency(), (int) $this->end, $this->pastDueOnly);
    }

    protected function calculate(): array
    {
        $customerIds = $this->data->getCustomerIds($this->customer);
        $lines = $this->getLines($customerIds);

        $currency = $this->getCurrency();
        $runningTotal = new Money($currency, 0);
        $runningBalance = new Money($currency, 0);
        $accountDetail = [];

        foreach ($lines as $line) {
            $runningTotal = $runningTotal->add($line->getLineTotal());
            $runningBalance = $runningBalance->add($line->getLineBalance());
            $accountDetail[] = $line->build();
        }

        return [
            // customer IDs
            'customerIds' => $customerIds,
            // account balance
            'totalInvoiced' => $runningTotal->toDecimal(),
            'balance' => $runningBalance->toDecimal(),
            'accountDetail' => $accountDetail,
            'aging' => $this->buildAging(),
            // balance forward fields
            'previousBalance' => 0,
            'totalPaid' => 0,
            'totalUnapplied' => 0,
            'unifiedDetail' => [],
            // credits
            // NOTE: open item statement does not have credit activity
            'previousCreditBalance' => 0,
            'totalCreditsIssued' => 0,
            'totalCreditsSpent' => 0,
            'creditDetail' => [],
            'creditBalance' => 0,
        ];
    }
}
