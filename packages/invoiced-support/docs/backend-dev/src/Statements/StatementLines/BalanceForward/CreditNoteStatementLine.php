<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Represents the application of a credit note to an invoice on
 * a balance forward statement line item.
 */
final class CreditNoteStatementLine implements BalanceForwardStatementLineInterface
{
    public function __construct(private CreditNote $creditNote, private TranslatorInterface $translator)
    {
    }

    public function getType(): string
    {
        return 'credit_note';
    }

    public function getDate(): int
    {
        return $this->creditNote->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $total = $this->creditNote->getTotal();
        $totals->subtractFromInvoiced($total)
            ->addAccountLine($this->buildRow($total, $totals));
    }

    /**
     * Builds a statement line item for a credit note activity.
     */
    private function buildRow(Money $total, BalanceForwardStatementTotals $totals): array
    {
        return [
            '_type' => $this->getType(),
            'type' => $this->translator->trans('labels.credit_note', [], 'pdf'),
            'customer' => $this->creditNote->customer(),
            'number' => $this->creditNote->number,
            'url' => $this->creditNote->url,
            'date' => $this->creditNote->date,
            'invoiced' => $total->negated()->toDecimal(),
            'amount' => $total->negated()->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
            'creditNote' => $this->creditNote,
        ];
    }
}
