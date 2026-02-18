<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\Libs\AbstractStatement;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PreviousBalanceStatementLine implements BalanceForwardStatementLineInterface
{
    public function __construct(private TranslatorInterface $translator, private int $date, private ?AbstractStatement $previousStatement)
    {
    }

    public function getType(): string
    {
        return 'previous_balance';
    }

    public function getDate(): int
    {
        return $this->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        // use any previous statement as the starting balance
        $currency = $totals->getRunningBalance()->currency;
        if ($this->previousStatement) {
            $previousBalance = Money::fromDecimal($currency, $this->previousStatement->balance);
            $previousCreditBalance = Money::fromDecimal($currency, $this->previousStatement->creditBalance);
        } else {
            $previousBalance = new Money($currency, 0);
            $previousCreditBalance = new Money($currency, 0);
        }

        $totals->setPreviousBalance($previousBalance)
            ->setPreviousCreditBalance($previousCreditBalance)
            ->addAccountLine($this->buildRow($previousBalance));
    }

    private function buildRow(Money $previousBalance): array
    {
        $label = $this->translator->trans('labels.previous_balance', [], 'pdf');

        return [
            '_type' => 'previous_balance',
            'type' => $label,
            'customer' => null,
            'number' => $label,
            'url' => null,
            'date' => $this->date,
            'amount' => $previousBalance->toDecimal(),
            'balance' => $previousBalance->toDecimal(),
        ];
    }
}
