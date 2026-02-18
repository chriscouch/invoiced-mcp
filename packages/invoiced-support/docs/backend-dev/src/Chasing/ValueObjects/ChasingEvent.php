<?php

namespace App\Chasing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Chasing\Models\ChasingCadenceStep;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Enums\StatementType;

/**
 * This represents a scheduled chasing activity.
 */
final class ChasingEvent
{
    public static function fromChasingBalance(ChasingBalance $chasingBalance, ChasingCadenceStep $step, ?ChasingCadenceStep $nextStep): self
    {
        return new self(
            $chasingBalance->getCustomer(),
            $chasingBalance->getBalance(),
            $chasingBalance->getPastDueBalance(),
            $chasingBalance->getInvoices(),
            $step,
            $nextStep);
    }

    /**
     * @param Invoice[] $invoices
     */
    public function __construct(private Customer $customer, private Money $balance, private Money $pastDueBalance, private array $invoices, private ChasingCadenceStep $step, private ?ChasingCadenceStep $nextStep = null)
    {
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getBalance(): Money
    {
        return $this->balance;
    }

    public function getPastDueBalance(): Money
    {
        return $this->pastDueBalance;
    }

    /**
     * @return Invoice[]
     */
    public function getInvoices(): array
    {
        return $this->invoices;
    }

    public function getStep(): ChasingCadenceStep
    {
        return $this->step;
    }

    public function getNextStep(): ?ChasingCadenceStep
    {
        return $this->nextStep;
    }

    /**
     * Builds the URL for the client to view their statement online.
     */
    public function getClientUrl(): string
    {
        return $this->customer->statement_url.'?type='.StatementType::OpenItem->value;
    }
}
