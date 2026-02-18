<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds a statement line item for a balance charge activity.
 */
final class AppliedCreditStatementLine implements BalanceForwardStatementLineInterface
{
    public function __construct(private Transaction $transaction, private TranslatorInterface $translator)
    {
    }

    public function getType(): string
    {
        return 'applied_credit';
    }

    public function getDate(): int
    {
        return $this->transaction->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $amount = $this->transaction->transactionAmount();
        $totals->subtractFromRunningBalance($amount)
            ->addToCreditsSpent($amount)
            ->addCreditLine($this->buildRow($amount, $totals));
    }

    private function buildRow(Money $amount, BalanceForwardStatementTotals $totals): array
    {
        $type = $description = $this->translator->trans('labels.applied_credit', [], 'pdf');
        $url = null;
        if ($invoice = $this->transaction->invoice()) {
            $description .= ': '.$invoice->number;
            $url = $invoice->url;
        } elseif ($estimate = $this->transaction->estimate()) {
            $description .= ': '.$estimate->number;
            $url = $estimate->url;
        }

        return [
            '_type' => 'adjustment',
            'type' => $type,
            'customer' => $this->transaction->customer(),
            'description' => $description,
            'number' => $description,
            'url' => $url,
            'date' => $this->transaction->date,
            'charged' => $amount->toDecimal(),
            'amount' => $amount->negated()->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
            'creditBalance' => $totals->getRunningCreditBalance()->toDecimal(),
        ];
    }
}
