<?php

namespace App\Integrations\QuickBooksOnline\Writers;

use App\AccountsReceivable\Libs\PaymentTermsFactory;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractInvoiceWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Traits\DocumentWriterTrait;
use App\Core\Orm\Model;

class QuickBooksInvoiceWriter extends AbstractInvoiceWriter
{
    use DocumentWriterTrait;

    public function __construct(private QuickBooksApi $quickbooksApi, protected QuickBooksCustomerWriter $customerWriter)
    {
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performCreate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            // create customer if no mapping exists
            $customer = $invoice->customer();
            if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
                $qboCustomerId = $customerMapping->accounting_id;
            } else {
                $qboCustomer = $this->customerWriter->createQBOCustomer($customer, $syncProfile);
                $qboCustomerId = (string) $qboCustomer->Id;
            }

            $this->createQBOInvoice($invoice, $qboCustomerId, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performUpdate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            $invoiceId = $invoiceMapping->accounting_id;

            // obtain QBO invoice data.
            $qboInvoice = $this->quickbooksApi->getInvoice($invoiceId);
            $invoiceSyncToken = (string) $qboInvoice->SyncToken;
            $qboCustomerId = (string) $qboInvoice->CustomerRef->value;

            // build update request params.
            $invoiceDetails = $this->buildQBOInvoiceDetails($invoice, $qboCustomerId, $syncProfile);
            $invoiceDetails['sparse'] = true;

            // update invoice in QBO.
            $this->quickbooksApi->updateInvoice($invoiceId, $invoiceSyncToken, $invoiceDetails);
        } catch (IntegrationApiException $e) {
            // When updating an invoice if we get an account period closed error
            // then we simply ignore it. When the accounting period is closed modifying
            // the invoice is not permitted.
            if (str_contains(strtolower($e->getMessage()), 'account period closed')) {
                return;
            }

            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performVoid(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            $invoiceId = $invoiceMapping->accounting_id;

            // obtain QBO invoice data.
            $qboInvoice = $this->quickbooksApi->getInvoice($invoiceId);
            $invoiceSyncToken = (string) $qboInvoice->SyncToken;

            // void invoice in QBO.
            $this->quickbooksApi->voidInvoice($invoiceId, $invoiceSyncToken);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Creates an invoice on QBO if no invoice exists
     * with the Invoiced's invoice's number. Creates
     * AccountingInvoiceMapping with QBO invoice's
     * details.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function createQBOInvoice(Invoice $invoice, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile, bool $saveMapping = true): \stdClass
    {
        if ('Convenience Fee' == $invoice->name) {
            if (!$syncProfile->write_convenience_fees) {
                throw new SyncException('Unable to write convenience fee invoice to QuickBooks Online. Writing convenience fees is disabled.');
            }
        } elseif (!$syncProfile->write_invoices) {
            throw new SyncException('Unable to write invoice to QuickBooks Online. Writing invoices is disabled.');
        }

        // check if invoice is present in QBO
        $docNumber = $this->formatDocumentNumber($invoice, $syncProfile);
        $qboInvoice = $this->quickbooksApi->getInvoiceByNumber($docNumber);
        $mappingSource = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM; // set created by QBO by default. Overwritten in if condition.

        if (!$qboInvoice) {
            // build QBO request params.
            $invoiceDetails = $this->buildQBOInvoiceDetails($invoice, $qboCustomerId, $syncProfile);

            // create invoice on QBO.
            $qboInvoice = $this->quickbooksApi->createInvoice($invoiceDetails);

            // created by Invoiced
            $mappingSource = AccountingInvoiceMapping::SOURCE_INVOICED;
        }

        if ($saveMapping) {
            $this->saveInvoiceMapping($invoice, $syncProfile->getIntegrationType(), $qboInvoice->Id, $mappingSource);
        }

        return $qboInvoice;
    }

    /**
     * Builds QBO invoice details structure using an Invoiced invoice.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildQBOInvoiceDetails(Invoice $invoice, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        $details = $this->buildQBODocumentDetails($invoice, $qboCustomerId, $syncProfile);

        // format due date for QBO
        $dueDate = $invoice->due_date
            ? date('Y-m-d', $invoice->due_date)
            : date('Y-m-d', $invoice->date);

        $details['DueDate'] = $dueDate;
        $details['CustomField'] = $this->buildQBOCustomFields($invoice, $syncProfile);

        if ($shipTo = $invoice->ship_to) {
            $details['ShipAddr'] = [
                'Line1' => $shipTo->address1,
                'Line2' => $shipTo->address2,
                'City' => $shipTo->city,
                'Country' => $shipTo->country,
                'CountrySubDivisionCode' => $shipTo->state,
                'PostalCode' => $shipTo->postal_code,
            ];
        }

        // get payment terms
        if ($termId = $this->getQBOPaymentTermId($invoice->payment_terms)) {
            $details['SalesTermRef'] = [
                'value' => $termId,
            ];
        }

        return $details;
    }

    /**
     * Builds custom fields for QBO invoice request.
     */
    public function buildQBOCustomFields(Invoice $invoice, AccountingSyncProfile $syncProfile): array
    {
        $customFields = [];

        // custom fields
        for ($i = 1; $i <= 3; ++$i) {
            $k = "custom_field_$i";
            $value = $syncProfile->$k;

            $parts = explode(':-:', (string) $value);
            if (3 !== count($parts)) {
                continue;
            }

            [$metadataId, $definitionId, $name] = $parts;
            if (!$metadataId || !$definitionId || !$name) {
                continue;
            }

            $metadata = $invoice->metadata;
            if ($stringValue = $metadata->$metadataId ?? null) {
                $customFields[] = [
                    'DefinitionId' => $definitionId,
                    'Type' => 'StringType',
                    'Name' => $name,
                    'StringValue' => $stringValue,
                ];
            }
        }

        return $customFields;
    }

    /**
     * Returns the ID of a QBO payment term given the payment terms
     * of an Invoiced invoice. If no payment term is found, one is
     * created.
     * Payment terms must be of the format 'X Y'.
     * i.e. NET 7.
     *
     * @throws IntegrationApiException
     */
    private function getQBOPaymentTermId(?string $term): ?string
    {
        if (!$term) {
            return null;
        }

        $paymentTerms = PaymentTermsFactory::get($term);
        if (!$paymentTerms->hasDueDate()) {
            return null;
        }

        // Format term
        $term = substr($term, 0, 31);

        // Format due days
        $dueDays = $paymentTerms->due_in_days;
        if ($dueDays > 999) {
            $dueDays = 999;
        }

        // look for matching term in QBO.
        if ($qboTerm = $this->quickbooksApi->getTermByName($term)) {
            return (string) $qboTerm->Id;
        }

        // qbo term has not been found, create one.
        $qboTerm = $this->quickbooksApi->createTerm([
            'Name' => $term,
            'DueDays' => $dueDays,
        ]);

        return (string) $qboTerm->Id;
    }
}
