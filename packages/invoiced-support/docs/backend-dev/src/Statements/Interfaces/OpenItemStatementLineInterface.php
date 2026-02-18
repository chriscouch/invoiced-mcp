<?php

namespace App\Statements\Interfaces;

use App\Core\I18n\ValueObjects\Money;

interface OpenItemStatementLineInterface
{
    /**
     * Builds a statement line.
     */
    public function build(): array;

    /**
     * Gets the date of the statement line.
     */
    public function getDate(): int;

    /**
     * Gets the amount of the statement line.
     * The amount is positive if the customer is being
     * billed and is negative if the customer is being
     * credited.
     */
    public function getLineTotal(): Money;

    /**
     * Gets the open balance of the statement line.
     * The amount is positive if the customer is being
     * billed and is negative if the customer is being
     * credited.
     */
    public function getLineBalance(): Money;
}
