<?php

namespace App\Statements\StatementLines;

use App\CashApplication\Models\Transaction;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Invoice;
use App\PaymentProcessing\Models\PaymentMethod;
use App\Statements\Interfaces\BalanceForwardStatementLineInterface;
use App\Statements\Libs\BalanceForwardStatement;
use App\Statements\StatementLines\BalanceForward\AppliedCreditStatementLine;
use App\Statements\StatementLines\BalanceForward\CreditBalanceAdjustmentStatementLine;
use App\Statements\StatementLines\BalanceForward\CreditNoteStatementLine;
use App\Statements\StatementLines\BalanceForward\DocumentAdjustmentStatementLine;
use App\Statements\StatementLines\BalanceForward\InvoiceStatementLine;
use App\Statements\StatementLines\BalanceForward\PaymentStatementLine;
use App\Statements\StatementLines\BalanceForward\PreviousBalanceStatementLine;
use App\Statements\StatementLines\BalanceForward\RefundStatementLine;
use App\Core\Orm\Model;
use Symfony\Contracts\Translation\TranslatorInterface;

class BalanceForwardStatementLineFactory
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * Converts a given model to a statement line, if it
     * should appear on the statement. If it should not
     * appear on the statement then the return of this
     * method will be null.
     *
     * This function assumes the input has been filtered
     * to exclude model types that should not be
     */
    public function make(Model $model): ?BalanceForwardStatementLineInterface
    {
        if ($model instanceof Invoice) {
            return new InvoiceStatementLine($model, $this->translator);
        }

        if ($model instanceof CreditNote) {
            return new CreditNoteStatementLine($model, $this->translator);
        }

        if ($model instanceof Transaction) {
            // Cash Receipt
            if (Transaction::TYPE_PAYMENT == $model->type) {
                if (!$model->parent_transaction) {
                    return new PaymentStatementLine($model, $this->translator);
                }
                $parent = $model->parentTransaction();
                if (Transaction::TYPE_CHARGE == $parent?->type) {
                    return new PaymentStatementLine($model, $this->translator);
                }
            }

            // Charge
            if (Transaction::TYPE_CHARGE == $model->type && PaymentMethod::BALANCE != $model->method && !$model->parent_transaction) {
                return new PaymentStatementLine($model, $this->translator);
            }

            // Document Adjustment
            if (Transaction::TYPE_DOCUMENT_ADJUSTMENT == $model->type) {
                return new DocumentAdjustmentStatementLine($model, $this->translator);
            }

            // Applied Credit Note + Charge/Cash Receipt
            if (Transaction::TYPE_ADJUSTMENT == $model->type && PaymentMethod::OTHER == $model->method && !$model->parent_transaction && $model->payment && $model->paymentAmount()->isPositive()) {
                return new PaymentStatementLine($model, $this->translator);
            }

            // Applied Credit
            if (Transaction::TYPE_CHARGE == $model->type && PaymentMethod::BALANCE == $model->method) {
                return new AppliedCreditStatementLine($model, $this->translator);
            }

            // Credit Balance Adjustment
            if (Transaction::TYPE_ADJUSTMENT == $model->type && PaymentMethod::BALANCE == $model->method) {
                return new CreditBalanceAdjustmentStatementLine($model, $this->translator);
            }

            // Refund
            if (Transaction::TYPE_REFUND == $model->type) {
                return new RefundStatementLine($model, $this->translator);
            }
        }

        return null;
    }

    /**
     * Converts a list of models into an array of
     * statement line objects.
     *
     * @return BalanceForwardStatementLineInterface[]
     */
    public function makeFromList(iterable $models): array
    {
        $lines = [];
        foreach ($models as $model) {
            if ($line = $this->make($model)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    public function makePreviousLine(int $date, ?BalanceForwardStatement $previousStatement): PreviousBalanceStatementLine
    {
        return new PreviousBalanceStatementLine($this->translator, $date, $previousStatement);
    }
}
