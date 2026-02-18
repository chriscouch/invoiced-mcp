<?php

namespace App\CashApplication\TransactionBuilder;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Interfaces\TransactionBuilderInterface;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Models\Charge;

abstract class AbstractSplit implements TransactionBuilderInterface
{
    /**
     * @throws ApplyPaymentException
     */
    protected function parsePaymentAmount(Payment $payment, array $split): Money
    {
        if (!isset($split['amount'])) {
            throw new ApplyPaymentException('Must provide an amount for each transaction.');
        }

        $amount = Money::fromDecimal($payment->currency, $split['amount']);
        if (!$amount->isPositive()) {
            throw new ApplyPaymentException('Amount applied to document must be greater than 0. Provided: '.$amount);
        }

        return $amount;
    }

    /**
     * Applies a charge to a transaction.
     */
    protected function applyCharge(Transaction $transaction, Charge $charge): void
    {
        if (Transaction::TYPE_ADJUSTMENT != $transaction->type) {
            $transaction->type = Transaction::TYPE_CHARGE;
        }

        $transaction->status = $charge->status;
        $transaction->gateway = $charge->gateway;
        $transaction->gateway_id = $charge->gateway_id;
        $transaction->notes = null;

        if ($source = $charge->payment_source) {
            $transaction->setPaymentSource($source);
        }

        if (Transaction::STATUS_FAILED == $transaction->status) {
            $transaction->failure_reason = $charge->failure_message;
        }
    }

    /**
     * @throws ApplyPaymentException
     */
    protected function validateCurrencyMatches(ReceivableDocument $document, Payment $payment): void
    {
        if ($document->currency != $payment->currency) {
            throw new ApplyPaymentException('The currency of '.strtolower($document::modelName()).' '.$document->number.' ('.$document->currency.') does not match the payment currency ('.$payment->currency.')');
        }
    }

    /**
     * @throws ApplyPaymentException
     */
    protected function validateCustomerMatches(ReceivableDocument $document, Payment $payment): void
    {
        /** @var Customer $customer */
        $customer = $payment->customer();
        if ($document->customer != $customer->id()) {
            $invoiceCustomer = $document->customer();
            if (!$customer->isParentOf($invoiceCustomer)) {
                throw new ApplyPaymentException('The '.strtolower($document::modelName()).' provided ('.$document->number.') does not belong to the customer that was selected to apply payments for: '.$customer->number);
            }
        }
    }
}
