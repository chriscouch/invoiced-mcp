<?php

namespace App\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractInvoiceWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Traits\IntacctEntityWriterTrait;
use App\Integrations\Intacct\Traits\WriterTrait;
use App\AccountsReceivable\Models\Invoice;
use Intacct\Functions\AbstractFunction;
use App\Core\Orm\Model;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

abstract class AbstractIntacctInvoiceWriter extends AbstractInvoiceWriter implements LoggerAwareInterface
{
    use WriterTrait, IntacctEntityWriterTrait, LoggerAwareTrait;

    public function __construct(
        protected IntacctApi $intacctApi,
        private IntacctCustomerWriter $customerWriter,
    ) {
    }

    /**
     * @throws TransformException
     */
    abstract protected function buildCreateRequest(Invoice $invoice, IntacctSyncProfile $syncProfile): AbstractFunction;

    /**
     * @throws TransformException
     */
    abstract protected function buildVoidRequest(Invoice $invoice, AccountingInvoiceMapping $mapping): AbstractFunction;

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performCreate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->intacctApi->setAccount($account);

        try {
            // look for customer, create if it doesn't exist
            $customerMapping = AccountingCustomerMapping::findForCustomer($invoice->customer(), $syncProfile->getIntegrationType());
            if (!$customerMapping) {
                $this->customerWriter->createIntacctCustomer($invoice->customer(), $syncProfile, $account);
            }

            $invoiceRequest = $this->buildCreateRequest($invoice, $syncProfile);
            $createdId = $this->createObjectWithEntityHandling($invoiceRequest, $account, $syncProfile, $invoice);
            $this->saveInvoiceMapping($invoice, $syncProfile->getIntegrationType(), $createdId, AccountingInvoiceMapping::SOURCE_INVOICED);
        } catch (IntegrationApiException|TransformException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performUpdate(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void
    {
        // Invoice updates are not currently supported yet. The
        // only update we can perform is a reversal in the case of
        // a void.
        // See: https://github.com/Intacct/intacct-sdk-php/issues/138
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performVoid(Invoice $invoice, Model $account, AccountingSyncProfile $syncProfile, AccountingInvoiceMapping $invoiceMapping): void
    {
        $this->intacctApi->setAccount($account);

        try {
            $voidInvoiceRequest = $this->buildVoidRequest($invoice, $invoiceMapping);
            $this->createObjectWithEntityHandling($voidInvoiceRequest, $account, $syncProfile, $invoice);
        } catch (IntegrationApiException|TransformException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    private function getIntacctEntity(Invoice|Payment|CreditNote $invoice): ?string
    {
        $metadata = $invoice->metadata;
        if ($metadata !== null && property_exists($metadata, 'intacct_entity')) {
            return $metadata->intacct_entity;
        }

        $customer = $invoice->customer();
        if ($customer === null) {
            return null;
        }

        return $this->customerWriter->getIntacctEntity($customer);
    }
}
