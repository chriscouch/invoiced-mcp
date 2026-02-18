<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\Customer;
use App\CashApplication\Enums\PaymentItemType;
use App\CashApplication\Models\Payment;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;
use App\Integrations\AccountingSync\Traits\AccountingLoaderTrait;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingPayment;
use App\Integrations\AccountingSync\ValueObjects\InvoicedPayment;

/**
 * This reads payments from the accounting system
 * with our local database.
 */
class AccountingPaymentLoader implements LoaderInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    use AccountingLoaderTrait;

    public function __construct(
        private AccountingCustomerLoader $customerLoader,
        private AccountingCreditNoteLoader $creditNoteLoader,
        private AccountingInvoiceLoader $invoiceLoader,
    ) {
    }

    /**
     * @param AccountingPayment $accountingPayment
     */
    public function load(AbstractAccountingRecord $accountingPayment): ImportRecordResult
    {
        $existingPayment = $this->findExisting($accountingPayment);

        // If the mapping already exists and Invoiced is the source of the record
        // then we do not proceed with updating the record from the accounting system values.
        if ($existingPayment && AccountingPaymentMapping::SOURCE_INVOICED == $existingPayment->mapping?->source) {
            return new ImportRecordResult($existingPayment->payment);
        }

        if ($existingPayment) {
            // Void an existing payment.
            if ($accountingPayment->voided) {
                return $this->voidPayment($accountingPayment, $existingPayment->payment);
            }

            // Delete an existing payment.
            if ($accountingPayment->deleted) {
                return $this->deletePayment($accountingPayment, $existingPayment->payment);
            }

            // Update an existing payment.
            return $this->updatePayment($accountingPayment, $existingPayment);
        }

        // If the payment does not exist and is already voided or deleted then we do not create it.
        if ($accountingPayment->voided || $accountingPayment->deleted) {
            return new ImportRecordResult();
        }

        // Create a new payment if it does not exist
        return $this->createPayment($accountingPayment);
    }

    public function findExisting(AccountingPayment $accountingPayment): ?InvoicedPayment
    {
        if ($accountingPayment->accountingId) {
            $mapping = AccountingPaymentMapping::findForAccountingId($accountingPayment->integration, $accountingPayment->accountingId);
        } else {
            $mapping = null;
        }

        // Look for an existing payment mapping using the accounting ID
        if (!$mapping instanceof AccountingPaymentMapping) {
            return null;
        }

        return new InvoicedPayment($mapping->payment, $mapping);
    }

    private function createPayment(AccountingPayment $accountingPayment): ImportRecordResult
    {
        $payment = new Payment();
        if (!$this->populatePayment($payment, $accountingPayment)) {
            return new ImportRecordResult();
        }

        if (!$payment->save()) {
            throw $this->makeException($accountingPayment, 'Could not create payment: '.$payment->getErrors());
        }

        // create a new mapping
        $this->saveMapping($payment, $accountingPayment);

        return $this->makeCreateResult($accountingPayment, $payment);
    }

    private function updatePayment(AccountingPayment $accountingPayment, InvoicedPayment $existingPayment): ImportRecordResult
    {
        $payment = $existingPayment->payment;

        // Do not attempt to update an already voided payment.
        if ($payment->voided) {
            return new ImportRecordResult($payment);
        }

        if (!$this->populatePayment($payment, $accountingPayment)) {
            return new ImportRecordResult($payment);
        }

        if (!$payment->save()) {
            throw $this->makeException($accountingPayment, 'Could not update payment: '.$payment->getErrors());
        }

        // create or update the mapping
        // the update is important because the accounting ID could have changed
        $mapping = $existingPayment->mapping ?? AccountingPaymentMapping::find($payment->id());
        $this->saveMapping($payment, $accountingPayment, $mapping);

        return $this->makeUpdateResult($accountingPayment, $payment);
    }

    /**
     * @throws LoadException if the delete fails
     */
    private function deletePayment(AccountingPayment $accountingPayment, Payment $payment): ImportRecordResult
    {
        if (!$payment->delete()) {
            throw $this->makeException($accountingPayment, 'Could not delete payment: '.$payment->getErrors());
        }

        return $this->makeDeleteResult($accountingPayment, $payment);
    }

    private function populatePayment(Payment $payment, AccountingPayment $accountingPayment): bool
    {
        foreach ($accountingPayment->values as $k => $v) {
            $payment->$k = $v;
        }

        $amount = $accountingPayment->getAmount();
        $payment->source = 'accounting_system';

        $appliedTo = [];
        foreach ($accountingPayment->appliedTo as $split) {
            $application = [
                'type' => $split->type,
                'amount' => $split->amount->toDecimal(),
            ];

            if ($accountingInvoice = $split->invoice) {
                $existingInvoice = $this->invoiceLoader->findExisting($accountingInvoice);
                if ($existingInvoice) {
                    $application['invoice'] = $existingInvoice->document;
                } else {
                    // reduce payment amount if this is an application of cash received
                    if (PaymentItemType::Invoice->value == $split->type) {
                        $amount = $amount->subtract($split->amount);
                    }

                    // skip if it does not exist
                    continue;
                }
            }

            if ($accountingCreditNote = $split->creditNote) {
                $existingCreditNote = $this->creditNoteLoader->findExisting($accountingCreditNote);
                if ($existingCreditNote) {
                    $application['credit_note'] = $existingCreditNote->document;
                } else {
                    // skip if it does not exist
                    continue;
                }
            }

            if ($split->documentType) {
                $application['document_type'] = $split->documentType;
            }

            $appliedTo[] = $application;
        }

        // Do not sync the payment if it does not have any line items
        if (0 == count($appliedTo)) {
            return false;
        }

        $payment->applied_to = $appliedTo;
        $payment->currency = $accountingPayment->currency;
        $payment->amount = $amount->toDecimal();

        // Populating the customer on the payment can potentially create a new customer.
        // We must do this after checking if the payment will be skipped because
        // it does not have any line items. Otherwise, we will get customers synced
        // for no reason.
        if ($accountingPayment->customer) {
            // Load the customer attached to the payment. This will create or update
            // the customer depending on whether there is a match in the system.
            $customerResult = $this->customerLoader->load($accountingPayment->customer);
            /** @var Customer $customer */
            $customer = $customerResult->getModel();
            $payment->setCustomer($customer);
        }

        $payment->skipReconciliation();

        return true;
    }

    private function voidPayment(AccountingPayment $accountingPayment, Payment $payment): ImportRecordResult
    {
        if ($payment->voided) {
            return new ImportRecordResult($payment);
        }

        try {
            $payment->void();
        } catch (ModelException $e) {
            throw $this->makeException($accountingPayment, $e->getMessage());
        }

        return $this->makeVoidResult($accountingPayment, $payment);
    }

    private function saveMapping(Payment $payment, AccountingPayment $accountingPayment, ?AccountingPaymentMapping $mapping = null): void
    {
        // Create a new mapping if one does not already exist. When creating
        // a new mapping here the record source is implied to be the accounting system.
        if (!$mapping) {
            $mapping = new AccountingPaymentMapping();
            $mapping->payment = $payment;
            $mapping->source = AccountingPaymentMapping::SOURCE_ACCOUNTING_SYSTEM;
        }

        $mapping->setIntegration($accountingPayment->integration);
        $mapping->accounting_id = $accountingPayment->accountingId;
        $mapping->save();
    }
}
