<?php

namespace App\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\PaymentMethod;

/**
 * Payment split which adjusts the balance of transactions.
 */
class DocumentAdjustmentSplit extends AbstractSplit
{
    private const ALLOWED_DOCUMENT_TYPES = [
        'credit_note',
        'estimate',
        'invoice',
    ];

    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);
        // build base transaction
        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_DOCUMENT_ADJUSTMENT;
        $transaction->method = PaymentMethod::OTHER;
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->payment = $payment;

        // determine the document type this split is being applied to
        $documentType = $split['document_type'] ?? null;
        if (!$documentType) {
            throw new ApplyPaymentException('Unspecified document type.');
        }

        return match ($documentType) {
            'credit_note' => [$this->applyToCreditNote($transaction, $amount, $split)],
            'estimate' => [$this->applyToEstimate($transaction, $amount, $split)],
            'invoice' => [$this->applyToInvoice($transaction, $amount, $split)],
            default => throw new ApplyPaymentException('Invalid document type provided. Allowed types are '.implode(', ', self::ALLOWED_DOCUMENT_TYPES)),
        };
    }

    /**
     * Sets a credit note instance on the provided transaction by looking it up
     * via the split information.
     *
     * @throws ApplyPaymentException
     */
    private function applyToCreditNote(Transaction $transaction, Money $amount, array $split): Transaction
    {
        // look up credit note
        $id = $split['credit_note'] ?? null;
        if ($id instanceof CreditNote) {
            $creditNote = $id;
        } else {
            $creditNote = CreditNote::find($id);
            if (!$creditNote) {
                throw new ApplyPaymentException('Could not find credit note: '.$id);
            }
        }

        $transaction->amount = $amount->negated()->toDecimal();
        $transaction->setCustomer($creditNote->customer());
        $transaction->setCreditNote($creditNote);

        return $transaction;
    }

    /**
     * Sets an estimate instance on the provided transaction by looking it up
     * via the split information.
     *
     * @throws ApplyPaymentException
     */
    private function applyToEstimate(Transaction $transaction, Money $amount, array $split): Transaction
    {
        // look up estimate
        $id = $split['estimate'] ?? null;
        if ($id instanceof Estimate) {
            $estimate = $id;
        } else {
            $estimate = Estimate::find($id);
            if (!$estimate) {
                throw new ApplyPaymentException('Could not find estimate: '.$id);
            }
        }

        $transaction->amount = $amount->toDecimal();
        $transaction->setCustomer($estimate->customer());
        $transaction->setEstimate($estimate);

        return $transaction;
    }

    /**
     * Sets a invoice instance on the provided transaction by looking it up
     * via the split information.
     *
     * @throws ApplyPaymentException
     */
    private function applyToInvoice(Transaction $transaction, Money $amount, array $split): Transaction
    {
        // look up invoice
        $id = $split['invoice'] ?? null;
        if ($id instanceof Invoice) {
            $invoice = $id;
        } else {
            $invoice = Invoice::find($id);
            if (!$invoice) {
                throw new ApplyPaymentException('Could not find invoice: '.$id);
            }
        }

        $transaction->amount = $amount->toDecimal();
        $transaction->setCustomer($invoice->customer());
        $transaction->setInvoice($invoice);

        return $transaction;
    }
}
