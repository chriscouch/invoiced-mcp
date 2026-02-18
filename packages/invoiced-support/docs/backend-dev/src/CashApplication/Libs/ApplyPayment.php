<?php

namespace App\CashApplication\Libs;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Exceptions\ApplyCreditBalancePaymentException;
use App\CashApplication\Exceptions\ApplyPaymentException;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\ActivityLog\Libs\EventSpool;
use App\Core\Orm\Exception\ModelException;
use App\Core\Orm\Model;
use Throwable;

class ApplyPayment
{
    /**
     * Applies payment to provided invoices, credits, and/or refunds.
     *
     * @throws ApplyPaymentException
     * @throws ApplyCreditBalancePaymentException
     *
     * @return Transaction[]
     */
    public function apply(Payment $payment, Customer $customer, array $splits): array
    {
        $modelsToCreate = $this->prepareResults($payment, $customer, $splits);
        $this->writeResults($payment, $modelsToCreate);

        return $modelsToCreate;
    }

    /**
     * Validate the request and prepare the transactions that need to be saved.
     *
     * @throws ApplyPaymentException
     */
    private function prepareResults(Payment $payment, Customer $customer, array $splits): array
    {
        if ($payment->voided) {
            throw new ApplyPaymentException('Cannot apply payment that is voided.');
        }

        if ($payment->applied) {
            throw new ApplyPaymentException('Cannot apply payment that is already applied.');
        }

        // The customer should be set here but not saved yet because the transaction builders
        // need to access the customer from the payment object.
        $payment->setCustomer($customer);

        // Build the transactions to be created.
        $handlers = [];
        $factory = new TransactionBuilderFactory();
        $modelsToCreate = [];
        $totalApplied = $payment->getExistingAmountApplied();
        foreach ($splits as $split) {
            // keep one instance of each split handler
            $type = $split['type'] ?? null;
            $handler = $handlers[$type] ?? null;
            if (!$handler) {
                $handler = $factory->make($split);
                $handlers[$type] = $handler;
            }

            $modelsToCreate = array_merge($modelsToCreate, $handler->build($payment, $split));

            if (!in_array($type, Payment::NON_CASH_SPLIT_TYPES)) {
                $totalApplied = $totalApplied->add(Money::fromDecimal($payment->currency, $split['amount']));
            }
        }

        // Validate the total being applied and update the payment balance.
        $paymentAmount = $payment->getAmount();
        if ($totalApplied->greaterThan($paymentAmount)) {
            throw new ApplyPaymentException('Total amount applied ('.$totalApplied.') cannot exceed the payment amount ('.$paymentAmount.')');
        }
        $payment->balance = max(0, $paymentAmount->subtract($totalApplied)->toDecimal());

        return $modelsToCreate;
    }

    /**
     * Saves the generated transactions and updates the payment.
     *
     * @param Model[] $modelsToCreate Transactions and credit notes to save
     *
     * @throws ApplyPaymentException
     * @throws ApplyCreditBalancePaymentException
     */
    private function writeResults(Payment $payment, array $modelsToCreate): void
    {
        try {
            /** @var Transaction|null $firstTransaction */
            $firstTransaction = Transaction::where('payment_id', $payment)
                ->where('parent_transaction IS NULL')
                ->oneOrNull();
            foreach ($modelsToCreate as $model) {
                if ($model instanceof Transaction) {
                    // Prior to each save, any cached models must be refreshed
                    // to account for changes that occurred due to a previous
                    // payment item or transaction deletion.
                    $this->refreshTransaction($model);

                    // The parent transaction is set on the subsequent transactions
                    // in order to maintain BC with accounting integrations until
                    // they have all been updated to support the payment object.
                    if ($firstTransaction) {
                        $model->setParentTransaction($firstTransaction);
                    }
                }

                $model->saveOrFail();

                if (!$firstTransaction && $model instanceof Transaction) {
                    $firstTransaction = $model;
                }
            }

            EventSpool::disablePush();
            $payment->saveOrFail();
            EventSpool::enablePop();
        } catch (Throwable $e) {
            if ($e instanceof ModelException) {
                if (isset($model) && $model->getErrors()->has('credit_balance', 'reason')) {
                    throw new ApplyCreditBalancePaymentException($e->getMessage());
                }
                throw new ApplyPaymentException($e->getMessage());
            }

            throw $e;
        }
    }

    private function refreshTransaction(Transaction $transaction): void
    {
        $creditNote = $transaction->creditNote();
        if ($creditNote instanceof Model) {
            $creditNote->refresh();
        }

        $estimate = $transaction->estimate();
        if ($estimate instanceof Model) {
            $estimate->refresh();
        }

        $invoice = $transaction->invoice();
        if ($invoice instanceof Model) {
            $invoice->refresh();
        }
    }
}
