<?php

namespace App\SalesTax\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use CommerceGuys\Addressing\Address;

/**
 * Represents an invoice for sales tax calculation purposes.
 */
class SalesTaxInvoice
{
    private ?string $number = null;
    private ?int $date = null;
    private ?int $taxDate = null;
    private int $discounts = 0;
    private bool $isPreview = false;
    private bool $isReturn = false;

    /**
     * @param \App\SalesTax\ValueObjects\SalesTaxInvoiceItem[] $lineItems
     */
    public function __construct(private Customer $customer, private Address $address, private string $currency, private array $lineItems, array $options = [])
    {
        if (isset($options['preview'])) {
            $this->isPreview = $options['preview'];
        }

        if (isset($options['return'])) {
            $this->isReturn = $options['return'];
        }

        if (isset($options['number'])) {
            $this->number = $options['number'];
        }

        if (isset($options['date']) && is_numeric($options['date'])) {
            $this->date = (int) $options['date'];
        }

        if (isset($options['taxDate']) && is_numeric($options['taxDate'])) {
            $this->taxDate = (int) $options['taxDate'];
        }

        if (isset($options['discounts'])) {
            $this->discounts = $options['discounts'];
        }
    }

    /**
     * Gets the customer that is making the purchases.
     */
    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * Gets the address used on the document for tax
     * calculation purposes. This can be the shipping
     * address or billing address, depending on what data
     * was available at the time of sale.
     */
    public function getAddress(): Address
    {
        return $this->address;
    }

    /**
     * Gets the currency of the document.
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Gets the document number, if available.
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    /**
     * Gets the document date, if available.
     */
    public function getDate(): ?int
    {
        return $this->date;
    }

    /**
     * Gets the tax date, if available.
     */
    public function getTaxDate(): ?int
    {
        return $this->taxDate;
    }

    /**
     * Gets the line items of the sales tax invoice.
     *
     * @return SalesTaxInvoiceItem[]
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * Checks if the calculation is for preview purposes,
     * as opposed to a finalized transaction.
     */
    public function isPreview(): bool
    {
        return $this->isPreview;
    }

    /**
     * Checks if the document being calculated is
     * a return of an invoice (aka credit note).
     */
    public function isReturn(): bool
    {
        return $this->isReturn;
    }

    /**
     * Gets the total amount of discounts, in cents.
     */
    public function getDiscounts(): int
    {
        return $this->discounts;
    }
}
