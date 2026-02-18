<?php

namespace App\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\PaymentProcessing\Models\PaymentMethod;

class AppliedCreditSplit extends AbstractSplit
{
    private static array $allowedDocumentTypes = ['estimate', 'invoice'];

    public function build(Payment $payment, array $split): array
    {
        $amount = $this->parsePaymentAmount($payment, $split);

        // build application transaction
        $transaction = new Transaction();
        $transaction->type = Transaction::TYPE_CHARGE;
        $transaction->method = PaymentMethod::BALANCE;
        $transaction->date = $payment->date;
        $transaction->currency = $amount->currency;
        $transaction->amount = $amount->toDecimal();
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
            if (!($document instanceof ReceivableDocument)) {
                $document = 'invoice' === $documentType ? Invoice::find($document) : Estimate::find($document);
            }

            if (!($document instanceof ReceivableDocument)) {
                throw new ApplyPaymentException('No such document: '.$split[$documentType]);
            }

            // perform validations
            $this->validateCurrencyMatches($document, $payment);
            $this->validateCustomerMatches($document, $payment);

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
            // should be the payment customer
            if ($customer = $payment->customer()) {
                $transaction->setCustomer($customer);
            }
        }

        // Charges
        if ($charge = $payment->charge) {
            $this->applyCharge($transaction, $charge);
        }

        return [$transaction];
    }
}
