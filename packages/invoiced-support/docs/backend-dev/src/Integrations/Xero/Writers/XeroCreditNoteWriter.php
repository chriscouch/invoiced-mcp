<?php

namespace App\Integrations\Xero\Writers;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractCreditNoteWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Libs\XeroApi;
use App\Integrations\Xero\Models\XeroAccount;
use App\Integrations\Xero\Models\XeroSyncProfile;
use App\Integrations\Xero\Traits\DocumentWriterTrait;
use App\AccountsReceivable\Models\CreditNote;
use App\Core\Orm\Model;
use stdClass;

class XeroCreditNoteWriter extends AbstractCreditNoteWriter
{
    use DocumentWriterTrait;

    public function __construct(private XeroApi $xeroApi, private XeroCustomerWriter $customerWriter, private string $dashboardUrl)
    {
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performCreate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->xeroApi->setAccount($account);

        try {
            // create customer if no mapping exists
            $customer = $creditNote->customer();
            if ($customerMapping = AccountingCustomerMapping::findForCustomer($customer, $syncProfile->getIntegrationType())) {
                $xeroCustomerId = $customerMapping->accounting_id;
            } else {
                $xeroCustomer = $this->customerWriter->createXeroCustomer($customer, $syncProfile);
                $xeroCustomerId = $xeroCustomer->ContactID;
            }

            $this->createXeroCreditNote($creditNote, $xeroCustomerId, $syncProfile);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performUpdate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void
    {
        $this->xeroApi->setAccount($account);

        try {
            $creditNoteId = $creditNoteMapping->accounting_id;

            // retrieve credit note from Xero
            $xeroCreditNote = $this->xeroApi->get('CreditNotes', $creditNoteId);
            $xeroCustomerId = $xeroCreditNote->Contact->ContactID;

            // when a credit note has allocations it cannot be edited
            if (count($xeroCreditNote->Allocations ?? []) > 0) {
                return;
            }

            // build the request
            $request = $this->buildRequest($creditNote, $xeroCustomerId, $syncProfile);
            $request['CreditNoteID'] = $creditNoteId;

            // send the update to xero
            $this->xeroApi->createOrUpdate('CreditNotes', $request);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param XeroAccount     $account
     * @param XeroSyncProfile $syncProfile
     */
    protected function performVoid(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void
    {
        $this->xeroApi->setAccount($account);

        try {
            $this->xeroApi->createOrUpdate('CreditNotes', [
                'CreditNoteID' => $creditNoteMapping->accounting_id,
                'Status' => 'VOIDED',
            ]);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Creates a credit note on Xero if no credit note exists
     * with the Invoiced's credit note number. Creates
     * AccountingCreditNoteMapping with Xero credit note's
     * details.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function createXeroCreditNote(CreditNote $creditNote, string $xeroCustomerId, XeroSyncProfile $syncProfile, bool $saveMapping = true): stdClass
    {
        // First check for an existing credit note on Xero
        $xeroCreditNote = $this->getExistingCreditNote($creditNote);
        $mappingSource = AccountingCreditNoteMapping::SOURCE_ACCOUNTING_SYSTEM; // set created by Xero by default. Overwritten in if condition.

        // If none found then create a new credit note
        if (!$xeroCreditNote) {
            if (!$syncProfile->write_credit_notes) {
                throw new SyncException('Unable to write credit note to Xero. Writing credit notes is disabled.');
            }

            $request = $this->buildRequest($creditNote, $xeroCustomerId, $syncProfile);
            $xeroCreditNote = $this->xeroApi->createOrUpdate('CreditNotes', $request);
            $mappingSource = AccountingCreditNoteMapping::SOURCE_INVOICED;
        }

        if ($saveMapping) {
            $this->saveCreditNoteMapping($creditNote, $syncProfile->getIntegrationType(), $xeroCreditNote->CreditNoteID, $mappingSource);
        }

        return $xeroCreditNote;
    }

    private function getExistingCreditNote(CreditNote $creditNote): ?stdClass
    {
        $number = $this->stripQuotes($creditNote->number);
        $xeroCreditNotes = $this->xeroApi->getMany('CreditNotes', [
            'where' => 'Type=="ACCRECCREDIT" AND CreditNoteNumber=="'.$number.'"',
        ]);

        return 1 == count($xeroCreditNotes) ? $xeroCreditNotes[0] : null;
    }

    public function buildRequest(CreditNote $creditNote, string $xeroCustomerId, XeroSyncProfile $syncProfile): array
    {
        $request = $this->buildDocumentRequest($creditNote, $xeroCustomerId, $syncProfile);
        $request['Type'] = 'ACCRECCREDIT';
        $request['CreditNoteNumber'] = $this->stripQuotes($creditNote->number);
        $request['Url'] = str_replace(':1236', '', $this->dashboardUrl).'/credit_notes/'.$creditNote->id();

        return $request;
    }
}
