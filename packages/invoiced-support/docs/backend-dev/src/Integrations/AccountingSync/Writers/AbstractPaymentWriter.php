<?php

namespace App\Integrations\AccountingSync\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Interfaces\AccountingWritableModelInterface;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\Enums\IntegrationType;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\ModelCreated;
use App\Core\Orm\Model;

abstract class AbstractPaymentWriter extends AbstractWriter
{
    /**
     * @throws SyncException
     */
    abstract protected function performCreate(Payment $payment, Customer $customer, Model $account, AccountingSyncProfile $syncProfile): void;

    /**
     * @throws SyncException
     */
    abstract protected function performUpdate(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void;

    /**
     * @throws SyncException
     */
    abstract protected function performVoid(Payment $payment, Model $account, AccountingSyncProfile $syncProfile, AccountingPaymentMapping $paymentMapping): void;

    public function isEnabled(AccountingSyncProfile $syncProfile): bool
    {
        return $syncProfile->write_payments;
    }

    /**
     * Checks if record was created after sync profile's start date.
     */
    public function shouldReconcile(Payment $payment, AccountingSyncProfile $syncProfile): bool
    {
        return CarbonImmutable::createFromTimestamp($payment->date)
            ->greaterThanOrEqualTo($syncProfile->getStartDate());
    }

    public function create(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        /** @var Payment $record */
        // do not write if payment is voided
        if ($record->voided) {
            return;
        }

        // check record date
        if (!$this->shouldReconcile($record, $syncProfile)) {
            return;
        }

        // do not write if payment is already created
        $integrationType = $syncProfile->getIntegrationType();
        if (AccountingPaymentMapping::findForPayment($record, $integrationType)) {
            return;
        }

        try {
            $customer = $record->customer();
            if (!$customer) {
                return;
            }

            $this->performCreate($record, $customer, $account, $syncProfile);
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (SyncException $e) {
            $this->handleSyncException($record, $integrationType, $e->getMessage(), ModelCreated::getName());
        }
    }

    public function update(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        /** @var Payment $record */
        // check record date
        if (!$this->shouldReconcile($record, $syncProfile)) {
            return;
        }

        $integrationType = $syncProfile->getIntegrationType();
        $paymentMapping = AccountingPaymentMapping::findForPayment($record, $integrationType);

        // create new payment if a mapping doesn't exist.
        if (!$paymentMapping) {
            $this->create($record, $account, $syncProfile);

            return;
        } elseif (AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM === $paymentMapping->source) {
            return; // Do not update payments that originated in the accounting system.
        }

        try {
            // void or update the payment depending on its state
            if ($record->voided) {
                $this->performVoid($record, $account, $syncProfile, $paymentMapping);
            } else {
                $this->performUpdate($record, $account, $syncProfile, $paymentMapping);
            }

            $this->updatePaymentMapping($paymentMapping);
            $this->handleSyncSuccess($record, $syncProfile);
        } catch (SyncException $e) {
            $this->handleSyncException($record, $integrationType, $e->getMessage(), ModelCreated::getName());
        }
    }

    public function delete(AccountingWritableModelInterface $record, Model $account, AccountingSyncProfile $syncProfile): void
    {
        // Deletes are not supported.
    }

    protected function buildConvenienceFeeInvoice(Payment $payment, Money $amount): Invoice
    {
        $feeInvoice = new Invoice();
        $feeInvoice->name = 'Convenience Fee';
        $feeInvoice->number = 'CF-'.$payment->id();
        $feeInvoice->currency = $amount->currency;
        $feeInvoice->date = time();
        $item = new LineItem();
        $item->name = 'Convenience Fee';
        $item->amount = $amount->toDecimal();
        $item->quantity = 1;
        $feeInvoice->items = [$item];

        return $feeInvoice;
    }

    //
    // Mappings
    //

    /**
     * Creates and stores a new AccountingPaymentMapping using
     * the given payment object and accountingId.
     */
    protected function savePaymentMapping(Payment $payment, IntegrationType $integrationType, string $accountingId): void
    {
        $mapping = new AccountingPaymentMapping();
        $mapping->source = AccountingPaymentMapping::SOURCE_INVOICED;
        $mapping->setIntegration($integrationType);
        $mapping->payment = $payment;
        $mapping->accounting_id = $accountingId;
        $mapping->save();
    }

    /**
     * Creates and saves an AccountingTransactionMapping for the given transaction.
     */
    protected function saveTransactionMapping(Transaction $transaction, IntegrationType $integrationType, string $accountingId): void
    {
        $mapping = new AccountingTransactionMapping();
        $mapping->source = AccountingTransactionMapping::SOURCE_INVOICED;
        $mapping->setIntegration($integrationType);
        $mapping->transaction = $transaction;
        $mapping->accounting_id = $accountingId;
        $mapping->save();
    }

    /**
     * Creates and stores a new AccountingConvenienceFeeMapping using
     * the given payment object and accountingId.
     */
    protected function saveConvenienceFeeMapping(Payment $payment, IntegrationType $integrationType, string $accountingId): void
    {
        $mapping = new AccountingConvenienceFeeMapping();
        $mapping->source = AccountingConvenienceFeeMapping::SOURCE_INVOICED;
        $mapping->setIntegration($integrationType);
        $mapping->payment = $payment;
        $mapping->accounting_id = $accountingId;
        $mapping->save();
    }

    /**
     * Updates a payment mapping with the latest sync time.
     */
    protected function updatePaymentMapping(AccountingPaymentMapping $mapping): void
    {
        $mapping->updated_at = time();
        $mapping->save();
    }

    /**
     * Adds mapping data to payment line items.
     */
    protected function buildEnrichedAppliedTo(Payment $payment, IntegrationType $integration): array
    {
        $appliedTo = $payment->applied_to;
        foreach ($appliedTo as &$lineItem) {
            $lineItem['type'] = PaymentItemType::from($lineItem['type']);

            if (isset($lineItem['invoice'])) {
                $lineItem['invoice'] = Invoice::findOrFail($lineItem['invoice']);
                $lineItem['invoiceMapping'] = AccountingInvoiceMapping::findForInvoice($lineItem['invoice'], $integration);
            }

            if (isset($lineItem['credit_note'])) {
                $lineItem['credit_note'] = CreditNote::findOrFail($lineItem['credit_note']);
                $lineItem['creditNoteMapping'] = AccountingCreditNoteMapping::findForCreditNote($lineItem['credit_note'], $integration);
            }

            $lineItem['amount'] = Money::fromDecimal($payment->currency, $lineItem['amount']);
        }

        return $appliedTo;
    }
}
