<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds a statement line item for a credit balance adjustment activity.
 */
final class CreditBalanceAdjustmentStatementLine implements BalanceForwardStatementLineInterface
{
    use HasPaymentStatementLineTrait;

    public function __construct(private Transaction $transaction, private TranslatorInterface $translator)
    {
    }

    public function getType(): string
    {
        return 'credit_balance_adjustment';
    }

    public function getDate(): int
    {
        return $this->transaction->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $amount = $this->transaction->transactionAmount()->negated();
        $totals->addToCreditsIssued($amount);

        if ($this->transaction->parent_transaction) {
            // If there is a parent transaction then we have an overpayment.
            // Overpayments should reduce the running balance since the
            // parent transaction increased it.
            $totals->addToRunningBalance($amount);
        } elseif ($this->transaction->credit_note_id) {
            // If there is a credit note associated then it must reduce
            // the running balance in order to offset the credit note.
            $totals->addToRunningBalance($amount);
        } else {
            // When we reach this branch we have a credit that was added
            // to the customer's credit balance without a corresponding
            // payment. This should be treated as a "payment" in order to
            // be counted towards "Payments & Credits".
            $totals->addToPaid($amount);
            if ($this->transaction->payment) {
                $totals->addAccountLine($this->buildPaymentRow($this->transaction, $this->translator, $amount, $totals));
            }
        }

        $totals->addCreditLine($this->buildRow($amount, $totals));
    }

    protected function buildRow(Money $amount, BalanceForwardStatementTotals $totals): array
    {
        if ($amount->isNegative()) {
            $description = $this->translator->trans('labels.adjustment', [], 'pdf');
        } else {
            $description = $this->translator->trans('labels.credit', [], 'pdf');
        }

        return [
            '_type' => 'adjustment',
            'customer' => $this->transaction->customer(),
            'description' => $description,
            'type' => $description,
            'date' => $this->transaction->date,
            'issued' => $amount->toDecimal(),
            'amount' => $amount->toDecimal(),
            'creditBalance' => $totals->getRunningCreditBalance()->toDecimal(),
        ];
    }
}
