<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds a statement line item for a refund.
 */
final class RefundStatementLine implements BalanceForwardStatementLineInterface
{
    public function __construct(private Transaction $transaction, private TranslatorInterface $translator)
    {
    }

    public function getType(): string
    {
        return 'refund';
    }

    public function getDate(): int
    {
        return $this->transaction->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $amount = $this->transaction->transactionAmount()->negated();
        $totals->addToPaid($amount);
        if (!$this->transaction->isConvenienceFee()) {
            $totals->subtractFromRunningBalance($amount);
        }
        $totals->addAccountLine($this->buildRow($amount, $totals));
    }

    private function buildRow(Money $amount, BalanceForwardStatementTotals $totals): array
    {
        $label = $this->translator->trans('labels.refund', [], 'pdf');
        $paymentSource = $this->transaction->payment_source;

        return [
            '_type' => 'refund',
            'type' => $label,
            'customer' => $this->transaction->customer(),
            'number' => $paymentSource?->toString(true),
            'date' => $this->transaction->date,
            'paid' => $amount->toDecimal(),
            'amount' => $amount->negated()->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
        ];
    }
}
