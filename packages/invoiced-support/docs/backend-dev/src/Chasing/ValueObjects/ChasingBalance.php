<?php

namespace App\Chasing\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;

final class ChasingBalance
{
    /**
     * @param Invoice[] $invoices
     */
    public function __construct(private Customer $customer, private array $invoices, private Money $balance, private Money $pastDueBalance, private int $age, private ?int $pastDueAge)
    {
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * Gets the age of the account.
     */
    public function getAge(): int
    {
        return $this->age;
    }

    /**
     * Checks if the account is past due.
     */
    public function isPastDue(): bool
    {
        return null !== $this->pastDueAge && $this->pastDueAge >= 0 && $this->pastDueBalance->isPositive();
    }

    /**
     * Gets the past due age of the account.
     */
    public function getPastDueAge(): ?int
    {
        return $this->pastDueAge;
    }

    /**
     * Gets the balance owed by the account.
     */
    public function getBalance(): Money
    {
        return $this->balance;
    }

    /**
     * Gets the past due balance owed by the account.
     */
    public function getPastDueBalance(): Money
    {
        return $this->pastDueBalance;
    }

    /**
     * Gets the invoices in the account.
     *
     * @return Invoice[]
     */
    public function getInvoices(): array
    {
        return $this->invoices;
    }
}
