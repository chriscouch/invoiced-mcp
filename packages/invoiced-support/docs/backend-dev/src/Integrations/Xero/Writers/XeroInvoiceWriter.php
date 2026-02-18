<?php

namespace App\Integrations\Xero\Writers;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractInvoiceWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Traits\DocumentWriterTrait;
use App\AccountsReceivable\Models\Invoice;
use Carbon\CarbonImmutable;
use App\Core\Orm\Model;
use stdClass;

class XeroInvoiceWriter extends AbstractInvoiceWriter
{
    use DocumentWriterTrait;

    public function __construct(private XeroApi $xeroApi, private XeroCustomerWriter $customerWriter, private string $dashboardUrl)
    {
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performCreate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->xeroApi->setAccount($account);

        try {
            // create customer if no mapping exists
            $customer = $invoice->customer();
            if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
                $xeroCustomerId = $customerMapping->accounting_id;
            } else {
                $xeroCustomer = $this->customerWriter->createXeroCustomer($customer, $syncProfile);
                $xeroCustomerId = $xeroCustomer->ContactID;
            }

            $this->createXeroInvoice($invoice, $xeroCustomerId, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performUpdate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void
    {
        $this->xeroApi->setAccount($account);

        try {
            $invoiceId = $invoiceMapping->accounting_id;

            // retrieve invoice from Xero
            $xeroInvoice = $this->xeroApi->get('Invoices', $invoiceId);
            $xeroCustomerId = $xeroInvoice->Contact->ContactID;

            // when an invoice has payments or credits applied it cannot be edited
            $paid = (float) ($xeroInvoice->AmountPaid ?? 0);
            $credited = (float) ($xeroInvoice->AmountCredited ?? 0);
            if ($paid > 0 || $credited > 0) {
                return;
            }

            // build the request
            $request = $this->buildRequest($invoice, $xeroCustomerId, $syncProfile);
            $request['InvoiceID'] = $invoiceId;

            // send it to xero
            $this->xeroApi->createOrUpdate('Invoices', $request);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performVoid(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void
    {
        $this->xeroApi->setAccount($account);

        try {
            $this->voidXeroInvoice($invoiceMapping->accounting_id);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Creates an invoice on Xero if no invoice exists
     * with the Invoiced's invoice's number. Creates
     * AccountingInvoiceMapping with Xero invoice's
     * details.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function createXeroInvoice(Invoice $invoice, string $xeroCustomerId, XeroSyncProfile $syncProfile, bool $saveMapping = true): stdClass
    {
        // First check for an existing invoice on Xero
        $xeroInvoice = $this->getExistingInvoice($invoice);
        $mappingSource = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM;

        // If none found then create a new customer
        if (!$xeroInvoice) {
            if ('Convenience Fee' == $invoice->name) {
                if (!$syncProfile->write_convenience_fees) {
                    throw new SyncException('Unable to write convenience fee invoice to Xero. Writing convenience fees is disabled.');
                }
            } elseif (!$syncProfile->write_invoices) {
                throw new SyncException('Unable to write invoice to Xero. Writing invoices is disabled.');
            }

            $request = $this->buildRequest($invoice, $xeroCustomerId, $syncProfile);
            $xeroInvoice = $this->xeroApi->createOrUpdate('Invoices', $request);
            $mappingSource = AccountingInvoiceMapping::SOURCE_INVOICED;
        }

        if ($saveMapping) {
            $this->saveInvoiceMapping($invoice, $syncProfile->getIntegrationType(), $xeroInvoice->InvoiceID, $mappingSource);
        }

        return $xeroInvoice;
    }

    /**
     * @throws IntegrationApiException
     */
    public function voidXeroInvoice(string $xeroId): void
    {
        $this->xeroApi->createOrUpdate('Invoices', [
            'Status' => 'VOIDED',
            'InvoiceID' => $xeroId,
        ]);
    }

    private function getExistingInvoice(Invoice $invoice): ?stdClass
    {
        $number = $this->stripQuotes($invoice->number);
        $xeroInvoices = $this->xeroApi->getMany('Invoices', [
            'where' => 'Type=="ACCREC" AND InvoiceNumber=="'.$number.'"',
        ]);

        return 1 == count($xeroInvoices) ? $xeroInvoices[0] : null;
    }

    /**
     * Builds Xero invoice details structure using an Invoiced invoice.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildRequest(Invoice $invoice, string $xeroCustomerId, XeroSyncProfile $syncProfile): array
    {
        $request = $this->buildDocumentRequest($invoice, $xeroCustomerId, $syncProfile);
        $request['Type'] = 'ACCREC';
        $request['InvoiceNumber'] = $this->stripQuotes($invoice->number);
        $request['Url'] = str_replace(':1236', '', $this->dashboardUrl).'/invoices/'.$invoice->id();

        if ($dueDate = $invoice->due_date) {
            $request['DueDate'] = CarbonImmutable::createFromTimestamp($dueDate)->toDateString();
        } else {
            $request['DueDate'] = $request['Date'];
        }

        return $request;
    }
}
