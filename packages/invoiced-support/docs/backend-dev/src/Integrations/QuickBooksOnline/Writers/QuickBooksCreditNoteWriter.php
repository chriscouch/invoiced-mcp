<?php

namespace App\Integrations\QuickBooksOnline\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractCreditNoteWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Libs\QuickBooksApi;
use App\Integrations\QuickBooksOnline\Models\QuickBooksAccount;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\Integrations\QuickBooksOnline\Traits\DocumentWriterTrait;
use App\Core\Orm\Model;

class QuickBooksCreditNoteWriter extends AbstractCreditNoteWriter
{
    use DocumentWriterTrait;

    public function __construct(private QuickBooksApi $quickbooksApi, private QuickBooksCustomerWriter $customerWriter)
    {
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performCreate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            // create customer if no mapping exists
            $customer = $creditNote->customer();
            if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
                $qboCustomerId = $customerMapping->accounting_id;
            } else {
                $qboCustomer = $this->customerWriter->createQBOCustomer($customer, $syncProfile);
                $qboCustomerId = (string) $qboCustomer->Id;
            }

            $this->createQBOCreditMemo($creditNote, $qboCustomerId, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param QuickBooksAccount           $account
     * @param QuickBooksOnlineSyncProfile $syncProfile
     */
    protected function performUpdate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void
    {
        $this->quickbooksApi->setAccount($account);

        try {
            $qboCreditMemoId = $creditNoteMapping->accounting_id;

            // obtain QBO credit memo data.
            $qboCreditMemo = $this->quickbooksApi->getCreditMemo($qboCreditMemoId);
            $creditMemoSyncToken = (string) $qboCreditMemo->SyncToken;
            $qboCustomerId = (string) $qboCreditMemo->CustomerRef->value;

            // build update request params.
            $creditMemoDetails = $this->buildQBODocumentDetails($creditNote, $qboCustomerId, $syncProfile);
            $creditMemoDetails['sparse'] = true;

            // update credit memo in QBO.
            $this->quickbooksApi->updateCreditMemo($qboCreditMemoId, $creditMemoSyncToken, $creditMemoDetails);
        } catch (IntegrationApiException $e) {
            // When updating a credit note if we get an account period closed error
            // then we simply ignore it. When the accounting period is closed modifying
            // the credit note is not permitted.
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
    protected function performVoid(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void
    {
        // QuickBooks Online does not allow voiding
        // credit memos through the API.
    }

    //
    // Helpers
    //

    /**
     * Creates a credit memo on QBO if no credit memo exists
     * with the Invoiced's credit note number. Creates
     * AccountingCreditNoteMapping with QBO credit memo's
     * details.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function createQBOCreditMemo(CreditNote $record, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile): \stdClass
    {
        if (!$syncProfile->write_credit_notes) {
            throw new SyncException('Unable to write credit note to QuickBooks Online. Writing credit notes is disabled.');
        }

        // check if credit note is present in QBO
        $docNumber = $this->formatDocumentNumber($record, $syncProfile);
        $qboCreditMemo = $this->quickbooksApi->getCreditMemoByNumber($docNumber);
        $mappingSource = AccountingInvoiceMapping::SOURCE_ACCOUNTING_SYSTEM; // set created by QBO by default. Overwritten in if condition.

        if (!$qboCreditMemo) {
            // build QBO request params.
            $creditMemoDetails = $this->buildQBODocumentDetails($record, $qboCustomerId, $syncProfile);

            // create credit memo on QBO.
            $qboCreditMemo = $this->quickbooksApi->createCreditMemo($creditMemoDetails);

            // created by Invoiced
            $mappingSource = AccountingInvoiceMapping::SOURCE_INVOICED;
        }

        $this->saveCreditNoteMapping($record, $syncProfile->getIntegrationType(), (string) $qboCreditMemo->Id, $mappingSource);

        return $qboCreditMemo;
    }
}
