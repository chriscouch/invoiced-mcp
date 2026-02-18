<?php

namespace App\Integrations\AccountingSync\Loaders;

use App\AccountsReceivable\Exceptions\AdjustBalanceException;
use App\AccountsReceivable\Libs\ReceivableBalanceAdjuster;
use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\DocumentPdfUploader;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Orm\Exception\ModelException;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Imports\ValueObjects\ImportRecordResult;
use App\Integrations\AccountingSync\Exceptions\LoadException;
use App\Integrations\AccountingSync\Interfaces\LoaderInterface;
use App\Integrations\AccountingSync\Models\AbstractMapping;
use App\Integrations\AccountingSync\Traits\AccountingLoaderTrait;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingDocument;
use App\Integrations\AccountingSync\ValueObjects\AbstractAccountingRecord;
use App\Integrations\AccountingSync\ValueObjects\AccountingCreditNote;
use App\Integrations\AccountingSync\ValueObjects\AccountingInvoice;
use App\Integrations\AccountingSync\ValueObjects\InvoicedDocument;
use mikehaertl\tmp\File;

abstract class AbstractDocumentLoader implements LoaderInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    use AccountingLoaderTrait;

    public function __construct(
        private AccountingCustomerLoader $customerLoader,
        private DocumentPdfUploader $pdfUploader,
    ) {
    }

    abstract public function findExisting(AbstractAccountingDocument $accountingDocument): ?InvoicedDocument;

    abstract public function makeNewModel(): ReceivableDocument;

    abstract public function makeNewMapping(ReceivableDocument $document): AbstractMapping;

    abstract public function findMapping(ReceivableDocument $document): ?AbstractMapping;

    /**
     * @param AbstractAccountingDocument $accountingDocument
     */
    public function load(AbstractAccountingRecord $accountingDocument): ImportRecordResult
    {
        $existingDocument = $this->findExisting($accountingDocument);

        // If the mapping already exists and Invoiced is the source of the record
        // then we do not proceed with updating the record from the accounting system values.
        if ($existingDocument && AbstractMapping::SOURCE_INVOICED == $existingDocument->mapping?->source) {
            return new ImportRecordResult($existingDocument->document);
        }

        if ($existingDocument) {
            // Void an existing document.
            if ($accountingDocument->voided) {
                return $this->voidDocument($accountingDocument, $existingDocument->document);
            }

            // Delete an existing document.
            if ($accountingDocument->deleted) {
                return $this->deleteDocument($accountingDocument, $existingDocument->document);
            }

            // Update an existing document.
            return $this->updateDocument($existingDocument, $accountingDocument);
        }

        // If the document does not exist and is already voided or deleted then we do not create it.
        if ($accountingDocument->voided || $accountingDocument->deleted) {
            return new ImportRecordResult();
        }

        // Create a new document.
        return $this->createDocument($accountingDocument);
    }

    /**
     * Creates a new document on Invoiced.
     *
     * @throws LoadException if the document creation fails
     */
    private function createDocument(AbstractAccountingDocument $accountingDocument): ImportRecordResult
    {
        $document = $this->makeNewModel();
        if ($accountingCustomer = $accountingDocument->customer) {
            // Load the customer attached to the payment. This will create or update
            // the customer depending on whether there is a match in the system.
            $customerResult = $this->customerLoader->load($accountingCustomer);
            /** @var Customer $customer */
            $customer = $customerResult->getModel();
            $document->setCustomer($customer);
        }

        // Since this is coming from the accounting system the taxes should never be assessed
        $document->calculate_taxes = false;

        foreach ($accountingDocument->values as $k => $v) {
            $document->$k = $v;
        }

        $document->skipReconciliation();

        if (!$document->save()) {
            // grab error messages, if creating the document fails
            throw $this->makeException($accountingDocument, 'Could not create '.$document::modelName().': '.$document->getErrors());
        }

        // create a new mapping
        $this->saveMapping($document, $accountingDocument);

        // save the PDF
        $this->savePdf($document, $accountingDocument);

        return $this->makeCreateResult($accountingDocument, $document);
    }

    /**
     * Updates an Invoiced document.
     *
     * @throws LoadException if the document update fails
     */
    protected function updateDocument(InvoicedDocument $existingDocument, AbstractAccountingDocument $accountingDocument): ImportRecordResult
    {
        $document = $existingDocument->document;
        $values = $accountingDocument->values;

        // If the document is voided on Invoiced then do not attempt to modify it
        if ($document->voided) {
            $this->updateMapping($existingDocument, $accountingDocument);

            return new ImportRecordResult($document);
        }

        // There shouldn't be an attempt to put a non-draft document
        // into a draft state.
        if (!$document->draft && ($values['draft'] ?? false)) {
            unset($values['draft']);
        }

        // If the invoice has a payment plan then we do not
        // want to overwrite the payment terms or due date.
        if ($document instanceof Invoice && $document->payment_plan_id) {
            unset($values['payment_terms']);
            unset($values['due_date']);
        }

        // If the invoice has AutoPay then we do not
        // want to overwrite the payment terms.
        if ($document instanceof Invoice && $document->autopay) {
            unset($values['payment_terms']);
        }

        // Since this is coming from the accounting system the taxes should never be assessed
        $document->calculate_taxes = false;

        foreach ($values as $k => $v) {
            // Make sure that existing metadata not included
            // in the import is not overwritten.
            if ('metadata' === $k) {
                $v = (object) array_filter(array_merge((array) $document->metadata, (array) $v));
            }

            $document->$k = $v;
        }

        $document->skipClosedCheck();
        $document->skipReconciliation();

        if (!$document->save()) {
            // grab error messages, if updating document fails
            throw $this->makeException($accountingDocument, 'Could not update '.$document::modelName().': '.$document->getErrors());
        }

        $this->updateMapping($existingDocument, $accountingDocument);
        // save the PDF
        $this->savePdf($document, $accountingDocument);

        return $this->makeUpdateResult($accountingDocument, $document);
    }

    /**
     * create or update the mapping
     * the update is important because the accounting ID could have changed.
     */
    protected function updateMapping(InvoicedDocument $existingDocument, AbstractAccountingDocument $accountingDocument): void
    {
        $document = $existingDocument->document;
        $mapping = $existingDocument->mapping ?? $this->findMapping($document);
        $this->saveMapping($document, $accountingDocument, $mapping);
    }

    /**
     * @throws LoadException
     */
    private function voidDocument(AbstractAccountingDocument $accountingDocument, ReceivableDocument $document): ImportRecordResult
    {
        if ($document->voided) {
            return new ImportRecordResult($document);
        }

        try {
            $document->void();
        } catch (ModelException $e) {
            throw $this->makeException($accountingDocument, $e->getMessage());
        }

        return $this->makeVoidResult($accountingDocument, $document);
    }

    /**
     * NOTE: The delete operation will fail if the document has any payments applied.
     *
     * @throws LoadException
     */
    private function deleteDocument(AbstractAccountingDocument $accountingDocument, ReceivableDocument $document): ImportRecordResult
    {
        if (!$document->delete()) {
            throw $this->makeException($accountingDocument, 'Could not delete '.$document::modelName().': '.$document->getErrors());
        }

        return $this->makeDeleteResult($accountingDocument, $document);
    }

    private function saveMapping(ReceivableDocument $document, AbstractAccountingDocument $accountingDocument, ?AbstractMapping $mapping = null): void
    {
        // Create a new mapping if one does not already exist. When creating
        // a new mapping here the record source is implied to be the accounting system.
        if (!$mapping) {
            $mapping = $this->makeNewMapping($document);
            $mapping->source = AbstractMapping::SOURCE_ACCOUNTING_SYSTEM;
        }

        $mapping->setIntegration($accountingDocument->integration);
        $mapping->accounting_id = $accountingDocument->accountingId;
        $mapping->save();
    }

    /**
     * @throws LoadException
     */
    private function savePdf(ReceivableDocument $document, AbstractAccountingDocument $accountingDocument): void
    {
        $pdf = $accountingDocument->pdf;
        if (!$pdf) {
            return;
        }

        // save it to a temporary file
        $tempFile = new File($pdf, 'pdf');
        $tempFile->delete = false; // handled by uploader

        // upload the temporary file
        try {
            $fileObject = $this->pdfUploader->upload($tempFile->getFileName(), $accountingDocument->getPdfFilename().'.pdf');

            // and replace the PDF for the document
            $this->pdfUploader->attachToDocument($document, $fileObject);
        } catch (UploadException|ModelException $e) {
            throw $this->makeException($accountingDocument, $e->getMessage());
        }
    }

    /**
     * @throws LoadException
     */
    protected function adjustBalance(CreditNote|Invoice $document, AccountingCreditNote|AccountingInvoice $accountingDocument, Money $balance): void
    {
        try {
            ReceivableBalanceAdjuster::sync($document, $balance);
        } catch (AdjustBalanceException $e) {
            throw $this->makeException($accountingDocument, $e->getMessage());
        }
    }
}
