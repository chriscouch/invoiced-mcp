<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds a statement line item for a payment.
 */
final class PaymentStatementLine implements BalanceForwardStatementLineInterface
{
    use HasPaymentStatementLineTrait;
    private Money $amount;

    public function __construct(private Transaction $transaction, private TranslatorInterface $translator)
    {
        $this->amount = $this->transaction->paymentAmount();
    }

    /**
     * Adds the convenience fee to this line item
     * which reduces the amount of the payment, since
     * convenience fees should not be included in statement
     * calculations and do not count towards the account balance.
     */
    public function addConvenienceFee(Money $amount): void
    {
        $this->amount = $this->amount->subtract($amount);
    }

    public function getType(): string
    {
        return 'payment';
    }

    public function getDate(): int
    {
        return $this->transaction->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $amount = $this->transaction->paymentAmount();
        $totals->addToPaid($amount)
            ->subtractFromRunningBalance($this->amount)
            ->addAccountLine($this->buildPaymentRow($this->transaction, $this->translator, $amount, $totals));
    }
}
