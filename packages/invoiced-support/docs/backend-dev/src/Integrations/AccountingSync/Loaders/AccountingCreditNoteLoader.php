<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Core\Files\Libs\DocumentPdfUploader;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingDocument;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\InvoicedDocument;

class AccountingCreditNoteLoader extends AbstractDocumentLoader
{
    public function __construct(
        AccountingCustomerLoader $customerLoader,
        DocumentPdfUploader $pdfUploader,
        private AccountingPaymentLoader $paymentLoader
    ) {
        parent::__construct($customerLoader, $pdfUploader);
    }

    public function findExisting(AbstractAccountingDocument $accountingDocument): ?InvoicedDocument
    {
        if ($accountingDocument->accountingId) {
            $mapping = AccountingCreditNoteMapping::findForAccountingId($accountingDocument->integration, $accountingDocument->accountingId);
        } else {
            $mapping = null;
        }

        $document = null;
        if ($mapping) {
            // Look for an existing document mapping using the accounting ID.
            $document = $mapping->credit_note;
        } elseif ($number = $accountingDocument->values['number'] ?? '') {
            // Look for existing document by document number.
            $document = CreditNote::where('number', $number)->oneOrNull();
        }

        return $document ? new InvoicedDocument($document, $mapping) : null;
    }

    public function makeNewModel(): ReceivableDocument
    {
        return new CreditNote();
    }

    /**
     * @param CreditNote $document
     */
    public function makeNewMapping(ReceivableDocument $document): AccountingCreditNoteMapping
    {
        $mapping = new AccountingCreditNoteMapping();
        $mapping->credit_note = $document;

        return $mapping;
    }

    public function findMapping(ReceivableDocument $document): ?AccountingCreditNoteMapping
    {
        return AccountingCreditNoteMapping::find($document->id());
    }

    /**
     * @param AccountingCreditNote $accountingDocument
     */
    public function load(AbstractAccountingRecord $accountingDocument): ImportRecordResult
    {
        $result = parent::load($accountingDocument);

        if ($result->wasCreated() || $result->wasUpdated()) {
            /** @var CreditNote $creditNote */
            $creditNote = $result->getModel();

            // create the payments
            $this->savePayments($creditNote, $accountingDocument);

            // adjust the balance
            if ($balance = $accountingDocument->balance) {
                $this->adjustBalance($creditNote, $accountingDocument, $balance);
            }
        }

        return $result;
    }

    /**
     * @param AccountingCreditNote $accountingDocument
     */
    protected function updateDocument(InvoicedDocument $existingDocument, AbstractAccountingDocument $accountingDocument): ImportRecordResult
    {
        // If the document is paid on Invoiced then do not attempt to modify it
        /** @var CreditNote $creditNote */
        $creditNote = $existingDocument->document;
        if ($creditNote->paid) {
            $this->updateMapping($existingDocument, $accountingDocument);

            // If there are credit note allocations to be saved then
            // we need to mark this as "updated" so that downstream
            // logic can apply the payments.
            if (null !== $accountingDocument->payments) {
                return $this->makeUpdateResult($accountingDocument, $creditNote);
            }

            return new ImportRecordResult($creditNote);
        }

        return parent::updateDocument($existingDocument, $accountingDocument);
    }

    /**
     * Applies payments to the credit note.
     */
    private function savePayments(CreditNote $creditNote, AccountingCreditNote $accountingCreditNote): void
    {
        // Do not perform any deletions if the credit note applications were not explicitly provided.
        if (null === $accountingCreditNote->payments) {
            return;
        }

        // Create/update credit note applications
        $paymentIds = [];
        foreach ($accountingCreditNote->payments as $application) {
            $result = $this->paymentLoader->load($application);
            if ($id = $result->getModel()?->id()) {
                $paymentIds[] = $id;
            }
        }

        // Delete any applications of credit note that were not just created or updated.
        // This is used because in some accounting systems payments are not available as
        // independent records. They can only be found on the credit note record. This works
        // contrary to how invoice payments work, for example, because those are available
        // as independent records that can be checked if they were voided.
        $query = Transaction::where('credit_note_id', $creditNote)
            ->where('payment_id', null, '<>');
        if (count($paymentIds) > 0) {
            $query->where('payment_id NOT IN ('.implode(',', $paymentIds).')');
        }
        /** @var Transaction[] $paymentsToDelete */
        $paymentsToDelete = $query->first(100);
        foreach ($paymentsToDelete as $transaction) {
            /** @var Payment $payment */
            $payment = $transaction->payment;
            if (!$payment->voided) {
                $payment->void();
            }
        }
    }
}
