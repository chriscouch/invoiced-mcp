<?php

namespace App\SalesTax\ValueObjects;

/**
 * Represents the line item within a sales tax invoice.
 */
class SalesTaxInvoiceItem
{
    /**
     * @param int $amount line item amount in cents
     */
    public function __construct(private string $description, private float $quantity, private int $amount, private ?string $itemCode = null, private bool $discountable = true)
    {
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getItemCode(): ?string
    {
        return $this->itemCode;
    }

    public function isDiscountable(): bool
    {
        return $this->discountable;
    }
}
