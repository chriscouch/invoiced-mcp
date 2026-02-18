<?php

namespace App\Statements\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Statements\Enums\StatementType;

/**
 * Builds statements from input parameters.
 */
class StatementBuilder
{
    public function __construct(
        private BalanceForwardStatementData $balanceForwardStatementData,
        private OpenItemStatementData $openItemStatementData
    ) {
    }

    /**
     * Builds a statement object for a customer.
     *
     * @throws \InvalidArgumentException when the arguments are invalid
     */
    public function build(Customer $customer, StatementType $type, ?string $currency = null, ?int $start = null, ?int $end = null, bool $pastDueOnly = false): AbstractStatement
    {
        if (StatementType::OpenItem == $type) {
            return $this->openItem($customer, $currency, $end, $pastDueOnly);
        }

        return $this->balanceForward($customer, $currency, $start, $end);
    }

    /**
     * Builds a balance forward statement for a customer.
     */
    public function balanceForward(Customer $customer, ?string $currency = null, ?int $start = null, ?int $end = null): BalanceForwardStatement
    {
        return new BalanceForwardStatement($this->balanceForwardStatementData, $customer, $currency, $start, $end);
    }

    /**
     * Builds an open item statement for a customer.
     */
    public function openItem(Customer $customer, ?string $currency = null, ?int $date = null, bool $pastDueOnly = false): OpenItemStatement
    {
        return new OpenItemStatement($this->openItemStatementData, $customer, $currency, $date, $pastDueOnly);
    }
}
