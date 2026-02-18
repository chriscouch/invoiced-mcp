<?php

namespace App\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;

class CreditNoteSplit extends AbstractSplit
{
    private static array $allowedDocumentTypes = ['estimate', 'invoice'];

    private array $creditNotes = [];
    private array $invoices = [];
    private array $estimates = [];

    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);

        // look up credit note
        $creditNote = $this->getCreditNote($split['credit_note']);

        // perform validations
        $this->validateCurrencyMatches($creditNote, $payment);
        $this->validateCustomerMatches($creditNote, $payment);

        // build application transaction
        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_ADJUSTMENT;
        $transaction->setCreditNote($creditNote);
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->amount = -$amount->toDecimal();
        $transaction->payment = $payment;

        // Determine if applied to balance or document
        $documentType = $split['document_type'] ?? null;

        // validate document info if provided
        if ($documentType) {
            // validate document type
            if (!in_array($documentType, self::$allowedDocumentTypes)) {
                throw new ApplyPaymentException('Invalid document type provided. Allowed types are '.implode(', ', self::$allowedDocumentTypes));
            }

            $document = $split[$documentType];
            if (!$document) {
                throw new ApplyPaymentException('Document type provided without document');
            }

            // look up document
            $document = 'invoice' === $documentType ? $this->getInvoice($document) : $this->getEstimate($document);

            // perform validations
            $this->validateCurrencyMatches($document, $payment);
            $this->validateCustomerMatches($document, $payment);

            $transaction->method = PaymentMethod::OTHER;
            if ($document instanceof Invoice) {
                $transaction->setInvoice($document);
            } elseif ($document instanceof Estimate) {
                $transaction->setEstimate($document);
            }

            // Transaction customer should always be the same as that of the document
            // the transaction is being applied to.
            $transaction->setCustomer($document->customer());
        } else {
            // Transaction is not being applied to a document so the customer
            // should be that of the credit note
            $transaction->setCustomer($creditNote->customer());

            // applied to balance
            $transaction->method = PaymentMethod::BALANCE;
        }

        // Charges
        if ($charge = $payment->charge) {
            $this->applyCharge($transaction, $charge);
        }

        return [$transaction];
    }

    private function getCreditNote(mixed $creditNote): CreditNote
    {
        if ($creditNote instanceof CreditNote) {
            $id = (int) $creditNote->id();
            if (!isset($this->creditNotes[$id])) {
                $this->creditNotes[$id] = $creditNote;
            }
        } else {
            $id = (int) $creditNote;
            if (!isset($this->creditNotes[$id])) {
                $model = CreditNote::find($id);
                if (!($model instanceof CreditNote)) {
                    throw new ApplyPaymentException('Could not find credit note: '.$id);
                }

                $this->creditNotes[$id] = $model;
            }
        }

        return $this->creditNotes[$id];
    }

    private function getInvoice(mixed $invoice): Invoice
    {
        if ($invoice instanceof Invoice) {
            $id = (int) $invoice->id();
            if (!isset($this->invoices[$id])) {
                $this->invoices[$id] = $invoice;
            }
        } else {
            $id = (int) $invoice;
            if (!isset($this->invoices[$id])) {
                $model = Invoice::find($id);
                if (!($model instanceof Invoice)) {
                    throw new ApplyPaymentException('Could not find invoice: '.$id);
                }

                $this->invoices[$id] = $model;
            }
        }

        return $this->invoices[$id];
    }

    private function getEstimate(mixed $estimate): Estimate
    {
        if ($estimate instanceof Estimate) {
            $id = (int) $estimate->id();
            if (!isset($this->estimates[$id])) {
                $this->estimates[$id] = $estimate;
            }
        } else {
            $id = (int) $estimate;
            if (!isset($this->estimates[$id])) {
                $model = Estimate::find($id);
                if (!($model instanceof Estimate)) {
                    throw new ApplyPaymentException('Could not find estimate: '.$id);
                }

                $this->estimates[$id] = $model;
            }
        }

        return $this->estimates[$id];
    }
}
