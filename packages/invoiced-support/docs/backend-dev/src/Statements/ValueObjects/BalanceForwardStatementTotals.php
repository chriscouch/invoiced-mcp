<?php

namespace App\Statements\ValueObjects;

use App\Core\I18n\ValueObjects\Money;

class BalanceForwardStatementTotals
{
    private Money $previousBalance;
    private Money $totalInvoiced;
    private Money $totalPaid;
    private Money $runningBalance;
    private Money $previousCreditBalance;
    private Money $totalCreditsIssued;
    private Money $totalCreditsSpent;
    private Money $runningCreditBalance;
    private array $accountDetail = [];
    private array $creditDetail = [];
    private array $unifiedDetail = [];

    public function __construct(string $currency)
    {
        $this->previousBalance = new Money($currency, 0);
        $this->totalInvoiced = new Money($currency, 0);
        $this->totalPaid = new Money($currency, 0);
        $this->runningBalance = new Money($currency, 0);
        $this->previousCreditBalance = new Money($currency, 0);
        $this->totalCreditsIssued = new Money($currency, 0);
        $this->totalCreditsSpent = new Money($currency, 0);
        $this->runningCreditBalance = new Money($currency, 0);
    }

    //
    // Account Balance
    //

    public function getPreviousBalance(): Money
    {
        return $this->previousBalance;
    }

    public function setPreviousBalance(Money $previousBalance): BalanceForwardStatementTotals
    {
        $this->previousBalance = $previousBalance;
        // Always add previous balance to running balance
        $this->addToRunningBalance($previousBalance);

        return $this;
    }

    public function getTotalInvoiced(): Money
    {
        return $this->totalInvoiced;
    }

    public function addToInvoiced(Money $amount): self
    {
        $this->totalInvoiced = $this->totalInvoiced->add($amount);
        // Always add amount invoiced to running balance
        $this->addToRunningBalance($amount);

        return $this;
    }

    public function subtractFromInvoiced(Money $amount): self
    {
        $this->totalInvoiced = $this->totalInvoiced->subtract($amount);
        // Always subtract amount from running balance
        $this->subtractFromRunningBalance($amount);

        return $this;
    }

    public function getTotalPaid(): Money
    {
        return $this->totalPaid;
    }

    public function addToPaid(Money $amount): self
    {
        $this->totalPaid = $this->totalPaid->add($amount);

        return $this;
    }

    public function getRunningBalance(): Money
    {
        return $this->runningBalance;
    }

    public function addToRunningBalance(Money $amount): self
    {
        $this->runningBalance = $this->runningBalance->add($amount);

        return $this;
    }

    public function subtractFromRunningBalance(Money $amount): self
    {
        $this->runningBalance = $this->runningBalance->subtract($amount);

        return $this;
    }

    //
    // Credit Balances
    //

    public function getPreviousCreditBalance(): Money
    {
        return $this->previousCreditBalance;
    }

    public function setPreviousCreditBalance(Money $previousCreditBalance): BalanceForwardStatementTotals
    {
        $this->previousCreditBalance = $previousCreditBalance;
        // Always add previous credit balance to running credit balance
        $this->runningCreditBalance = $this->runningCreditBalance->add($previousCreditBalance);

        return $this;
    }

    public function getTotalCreditsIssued(): Money
    {
        return $this->totalCreditsIssued;
    }

    public function addToCreditsIssued(Money $amount): self
    {
        $this->totalCreditsIssued = $this->totalCreditsIssued->add($amount);
        // Always add credits issued to running credit balance
        $this->runningCreditBalance = $this->runningCreditBalance->add($amount);

        return $this;
    }

    public function getTotalCreditsSpent(): Money
    {
        return $this->totalCreditsSpent;
    }

    public function addToCreditsSpent(Money $amount): self
    {
        $this->totalCreditsSpent = $this->totalCreditsSpent->add($amount);
        // Always subtract credits spent from running credit balance
        $this->runningCreditBalance = $this->runningCreditBalance->subtract($amount);

        return $this;
    }

    public function getRunningCreditBalance(): Money
    {
        return $this->runningCreditBalance;
    }

    //
    // Line Items
    //

    public function getAccountDetail(): array
    {
        return $this->accountDetail;
    }

    public function addAccountLine(array $line): self
    {
        $this->accountDetail[] = $line;
        $this->unifiedDetail[] = $line;

        return $this;
    }

    public function getCreditDetail(): array
    {
        return $this->creditDetail;
    }

    public function addCreditLine(array $line): self
    {
        $this->creditDetail[] = $line;
        $this->unifiedDetail[] = $line;

        return $this;
    }

    public function getUnifiedDetail(): array
    {
        return $this->unifiedDetail;
    }
}
