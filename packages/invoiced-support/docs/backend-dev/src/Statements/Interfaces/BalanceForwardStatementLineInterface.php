<?php

namespace App\Statements\Interfaces;

use App\Statements\ValueObjects\BalanceForwardStatementTotals;

/**
 * Represents a line in a balance forward statement.
 */
interface BalanceForwardStatementLineInterface
{
    /**
     * Return the string "type" for each statement document input
     * to be used in the templates
     * List of available types:
     * - invoice
     * - credit_note
     * - charge
     * - payment
     * - refund
     * - adjustment.
     */
    public function getType(): string;

    /**
     * Gets the timestamp of this entry.
     */
    public function getDate(): int;

    /**
     * Applies changes to the input parameters according to the nested
     * models rules. And formats credit and account statement rows
     * based on applied values.
     */
    public function apply(BalanceForwardStatementTotals $totals): void;
}
