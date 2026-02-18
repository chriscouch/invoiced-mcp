<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\InvoiceDistribution;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Models\AccountingConvenienceFeeMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingDocument;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\InvoicedDocument;
use App\PaymentPlans\Models\PaymentPlan;
use App\PaymentPlans\Models\PaymentPlanInstallment;

class AccountingInvoiceLoader extends AbstractDocumentLoader
{
    public function findExisting(AbstractAccountingDocument $accountingDocument): ?InvoicedDocument
    {
        if ($accountingDocument->accountingId) {
            $mapping = AccountingInvoiceMapping::findForAccountingId($accountingDocument->integration, $accountingDocument->accountingId);
        } else {
            $mapping = null;
        }

        $document = null;
        if ($mapping) {
            // Look for an existing document mapping using the accounting ID.
            $document = $mapping->invoice;
        } elseif ($number = $accountingDocument->values['number'] ?? '') {
            // Look for existing document by document number.
            $document = Invoice::where('number', $number)->oneOrNull();
        }

        return $document ? new InvoicedDocument($document, $mapping) : null;
    }

    public function makeNewModel(): ReceivableDocument
    {
        return new Invoice();
    }

    /**
     * @param Invoice $document
     */
    public function makeNewMapping(ReceivableDocument $document): AccountingInvoiceMapping
    {
        $mapping = new AccountingInvoiceMapping();
        $mapping->invoice = $document;

        return $mapping;
    }

    public function findMapping(ReceivableDocument $document): ?AccountingInvoiceMapping
    {
        return AccountingInvoiceMapping::find($document->id());
    }

    /**
     * @param AccountingInvoice $accountingDocument
     */
    public function load(AbstractAccountingRecord $accountingDocument): ImportRecordResult
    {
        if (!$this->beforeLoad($accountingDocument)) {
            return new ImportRecordResult();
        }

        $result = parent::load($accountingDocument);
        $this->afterLoad($accountingDocument, $result);

        return $result;
    }

    /**
     * Adds customization to the loading process that happen BEFORE calling the parent class.
     */
    private function beforeLoad(AccountingInvoice $accountingInvoice): bool
    {
        // Check if the invoice was from an Invoiced convenience fee
        // and do not load if it is
        $feeMapping = AccountingConvenienceFeeMapping::findForAccountingId($accountingInvoice->integration, $accountingInvoice->accountingId);
        if ($feeMapping instanceof AccountingConvenienceFeeMapping) {
            return false;
        }

        return true;
    }

    /**
     * Adds customization to the loading process that happen AFTER calling the parent class.
     */
    private function afterLoad(AccountingInvoice $accountingInvoice, ImportRecordResult $result): void
    {
        if ($result->wasCreated() || $result->wasUpdated()) {
            /** @var Invoice $invoice */
            $invoice = $result->getModel();

            // create the payment plan
            if ($accountingInvoice->installments) {
                $paymentPlan = $this->createPaymentPlan($invoice, $accountingInvoice->installments);

                if (!$invoice->attachPaymentPlan($paymentPlan, false, true)) {
                    throw $this->makeException($accountingInvoice, 'Could not save installment plan: '.$invoice->getErrors());
                }
            }

            // adjust the balance
            if ($balance = $accountingInvoice->balance) {
                $this->adjustBalance($invoice, $accountingInvoice, $balance);
            }

            // The contact list and distribution list are legacy features.
            // These could be replaced with invoice chasing instead.
            // update the contact list
            if ($accountingInvoice->contactList) {
                $this->updateDepartmentContactList($accountingInvoice, $invoice->customer(), $accountingInvoice->contactList['department'], $accountingInvoice->contactList['emails']);
            }

            // set the distribution list
            if ($accountingInvoice->distributionSettings) {
                if ($result->wasUpdated()) {
                    $this->createDistributionSettings($accountingInvoice, $invoice, $accountingInvoice->distributionSettings);
                } else {
                    $this->updateDistributionSettings($accountingInvoice, $invoice, $accountingInvoice->distributionSettings);
                }
            }

            if ($accountingInvoice->delivery) {
                try {
                    $invoice->createInvoiceDelivery($accountingInvoice->delivery);
                } catch (ModelException) {
                    // do nothing
                }
            }
        }
    }

    /**
     * @param AccountingInvoice $accountingDocument
     */
    protected function updateDocument(InvoicedDocument $existingDocument, AbstractAccountingDocument $accountingDocument): ImportRecordResult
    {
        // If the document is paid on Invoiced then do not attempt to modify it
        /** @var Invoice $invoice */
        $invoice = $existingDocument->document;
        if ($invoice->paid) {
            $this->updateMapping($existingDocument, $accountingDocument);

            return new ImportRecordResult($invoice);
        }

        // If importing installments then the payment plan must
        // be canceled prior to importing.
        if ($accountingDocument->installments && $paymentPlan = $invoice->paymentPlan()) {
            $paymentPlan->cancel();
        }

        return parent::updateDocument($existingDocument, $accountingDocument);
    }

    private function createPaymentPlan(Invoice $invoice, array $installmentsValues): PaymentPlan
    {
        $installments = [];
        $lineItems = $invoice->items;

        $currency = $invoice->currency;
        $amountPaid = Money::fromDecimal($currency, $invoice->amount_paid);

        foreach ($lineItems as $i => $lineItem) {
            $lineItemAmount = Money::fromDecimal($currency, $lineItem->amount);

            if ($lineItemAmount->isPositive()) {
                $balancePaid = $amountPaid;
                if ($amountPaid->greaterThan($lineItemAmount)) {
                    $balancePaid = $lineItemAmount;
                }

                $amountPaid = $amountPaid->subtract($balancePaid);
                $balance = $lineItemAmount->subtract($balancePaid);

                $installmentValues = $installmentsValues[$i];
                $installments[] = $this->createPaymentPlanInstallment($lineItemAmount, $balance, $installmentValues);
            }
        }

        $paymentPlan = new PaymentPlan();
        $paymentPlan->installments = $installments;

        return $paymentPlan;
    }

    private function createPaymentPlanInstallment(Money $amount, Money $balance, array $values): PaymentPlanInstallment
    {
        $installment = new PaymentPlanInstallment();

        foreach ($values as $key => $value) {
            $installment->$key = $values[$key];
        }

        $installment->amount = $amount->toDecimal();
        $installment->balance = $balance->toDecimal();

        return $installment;
    }

    /**
     * Sets the distribution settings for a new invoice.
     *
     * @throws LoadException
     */
    private function createDistributionSettings(AccountingInvoice $accountingInvoice, Invoice $invoice, array $distributionList): InvoiceDistribution
    {
        $distribution = new InvoiceDistribution();
        $distribution->setInvoice($invoice);
        foreach ($distributionList as $k => $v) {
            $distribution->$k = $v;
        }

        if (!$distribution->save()) {
            // grab error messages, if operation fails
            throw $this->makeException($accountingInvoice, 'Could not create invoice distribution settings: '.$distribution->getErrors());
        }

        return $distribution;
    }

    /**
     * Updates the distribution settings for an existing invoice.
     *
     * @throws LoadException
     */
    private function updateDistributionSettings(AccountingInvoice $accountingInvoice, Invoice $invoice, array $distributionList): InvoiceDistribution
    {
        $distribution = InvoiceDistribution::where('invoice_id', $invoice->id())->oneOrNull();
        if (!$distribution) {
            $distribution = new InvoiceDistribution();
            $distribution->setInvoice($invoice);
        }

        foreach ($distributionList as $k => $v) {
            $distribution->$k = $v;
        }

        if (!$distribution->save()) {
            // grab error messages, if operation fails
            throw $this->makeException($accountingInvoice, 'Could not update invoice distribution settings: '.$distribution->getErrors());
        }

        return $distribution;
    }

    /**
     * @throws LoadException
     */
    private function updateDepartmentContactList(AccountingInvoice $accountingInvoice, Customer $customer, string $department, array $emails): void
    {
        // find any existing contacts with the department name
        $contacts = Contact::where('customer_id', $customer->id())
            ->where('department', $department)
            ->first(100);

        // figure out the contacts that need to be added and deleted
        $emailsToAdd = array_unique($emails);
        foreach ($contacts as $contact) {
            $index = array_search($contact->email, $emailsToAdd);
            if (false === $index) {
                if (!$contact->delete()) {
                    // grab error messages, if operation fails
                    throw $this->makeException($accountingInvoice, 'Could not delete contact: '.$contact->getErrors());
                }
            } else {
                unset($emailsToAdd[$index]);
            }
        }

        // add the remaining contacts
        foreach ($emailsToAdd as $email) {
            $contact = new Contact();
            $contact->customer = $customer;
            $contact->name = $department;
            $contact->department = $department;
            $contact->email = $email;
            $contact->primary = false;

            if (!$contact->save()) {
                // grab error messages, if operation fails
                throw $this->makeException($accountingInvoice, 'Could not create distribution list: '.$contact->getErrors());
            }
        }
    }
}
