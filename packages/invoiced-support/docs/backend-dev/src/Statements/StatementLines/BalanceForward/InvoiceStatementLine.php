<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\Invoice;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Represents an invoice line on a balance forward statement.
 */
final class InvoiceStatementLine implements BalanceForwardStatementLineInterface
{
    public function __construct(private Invoice $invoice, private TranslatorInterface $translator)
    {
    }

    public function getType(): string
    {
        return 'invoice';
    }

    public function getDate(): int
    {
        return $this->invoice->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $total = $this->invoice->getTotal();
        $totals->addToInvoiced($total)
            ->addAccountLine($this->buildRow($total, $totals));
    }

    private function buildRow(Money $total, BalanceForwardStatementTotals $totals): array
    {
        return [
            '_type' => $this->getType(),
            'type' => $this->translator->trans('labels.invoice', [], 'pdf'),
            'customer' => $this->invoice->customer(),
            'number' => $this->invoice->number,
            'url' => $this->invoice->url,
            'date' => $this->invoice->date,
            'invoiced' => $total->toDecimal(),
            'amount' => $total->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
            'invoice' => $this->invoice,
        ];
    }
}
