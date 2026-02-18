<?php

namespace App\Network\Ubl\Traits;

use Money\Money;

trait LegalMonetaryTotalTrait
{
    private ?Money $subtotal = null;
    private ?Money $totalAllowances = null;
    private ?Money $totalTax = null;
    private ?Money $totalCharges = null;
    private ?Money $total = null;
    private ?Money $amountPaid = null;
    private ?Money $balance = null;

    public function getSubtotal(): ?Money
    {
        return $this->subtotal;
    }

    public function setSubtotal(?Money $subtotal): void
    {
        $this->subtotal = $subtotal;
        if (null === $this->summaryTotal && $subtotal) {
            $this->summaryTotal = $subtotal;
        }
    }

    public function getTotalTax(): ?Money
    {
        return $this->totalTax;
    }

    public function setTotalTax(?Money $totalTax): void
    {
        $this->totalTax = $totalTax;
    }

    public function getTotalAllowances(): ?Money
    {
        return $this->totalAllowances;
    }

    public function setTotalAllowances(?Money $totalAllowances): void
    {
        $this->totalAllowances = $totalAllowances;
    }

    public function getTotalCharges(): ?Money
    {
        return $this->totalCharges;
    }

    public function setTotalCharges(?Money $totalCharges): void
    {
        $this->totalCharges = $totalCharges;
    }

    public function getTotal(): ?Money
    {
        return $this->total;
    }

    public function setTotal(?Money $total): void
    {
        $this->total = $total;
        if ($total) {
            $this->summaryTotal = $total;
        }
    }

    public function getAmountPaid(): ?Money
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(?Money $amountPaid): void
    {
        $this->amountPaid = $amountPaid;
    }

    public function getBalance(): ?Money
    {
        return $this->balance;
    }

    public function setBalance(?Money $balance): void
    {
        $this->balance = $balance;
        if (null === $this->summaryTotal && $balance) {
            $this->summaryTotal = $balance;
        }
    }
}
