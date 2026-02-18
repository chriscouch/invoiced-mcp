<?php

namespace App\Statements\StatementLines\BalanceForward;

use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\ValueObjects\BalanceForwardStatementTotals;
use Symfony\Contracts\Translation\TranslatorInterface;

class DocumentAdjustmentStatementLine implements BalanceForwardStatementLineInterface
{
    use HasPaymentStatementLineTrait;

    public function __construct(private Transaction $transaction, private TranslatorInterface $translator)
    {
    }

    public function getType(): string
    {
        return PaymentItemType::DocumentAdjustment->value;
    }

    public function getDate(): int
    {
        return $this->transaction->date;
    }

    public function apply(BalanceForwardStatementTotals $totals): void
    {
        $amount = $this->transaction->transactionAmount();
        /** @var ReceivableDocument $document */
        $document = $this->transaction->invoice() ?? $this->transaction->creditNote() ?? $this->transaction->estimate();
        $totals->addToPaid($amount)
            ->subtractFromRunningBalance($amount)
            ->addAccountLine($this->buildRow($amount, $totals, $document));
    }

    protected function buildRow(Money $amount, BalanceForwardStatementTotals $totals, ReceivableDocument $document): array
    {
        $adjustmentStr = $this->translator->trans('labels.adjustment', [], 'pdf');
        $description = $adjustmentStr.' ('.$document->number.')';

        return [
            '_type' => 'adjustment',
            'type' => $adjustmentStr,
            'customer' => $this->transaction->customer(),
            'number' => $description,
            'description' => $description,
            'date' => $this->transaction->date,
            'paid' => $amount->toDecimal(),
            'amount' => $amount->negated()->toDecimal(),
            'balance' => $totals->getRunningBalance()->toDecimal(),
        ];
    }
}
