<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

trait HasPaymentStatementLineTrait
{
    protected function buildPaymentRow(Transaction $transaction, TranslatorInterface $translator, Money $amount, BalanceForwardStatementTotals $totals): array
    {
        $label = $translator->trans('labels.payment', [], 'pdf');
        $paymentSource = $transaction->payment_source;

        return [
            '_type' => 'payment',
            'type' => $label,
            'customer' => $transaction->customer(),
            'number' => $paymentSource ? $paymentSource->toString(true) : ($transaction->gateway_id ?? $label),
            'date' => $transaction->date,
            'paid' => $amount->toDecimal(),
            'amount' => $amount->negated()->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
        ];
    }
}
