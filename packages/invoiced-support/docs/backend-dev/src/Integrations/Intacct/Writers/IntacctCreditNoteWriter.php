<?php

namespace App\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\CashApplication\Models\Payment;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingCreditNoteMapping;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\Writers\AbstractCreditNoteWriter;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Intacct\Libs\IntacctApi;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Intacct\Traits\IntacctEntityWriterTrait;
use App\Integrations\Intacct\Traits\WriterTrait;
use Carbon\Carbon;
use Intacct\Functions\AccountsReceivable\ArAdjustmentCreate;
use Intacct\Functions\AccountsReceivable\ArAdjustmentDelete;
use Intacct\Functions\AccountsReceivable\ArAdjustmentLineCreate;
use App\Core\Orm\Model;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class IntacctCreditNoteWriter extends AbstractCreditNoteWriter implements LoggerAwareInterface
{
    use WriterTrait, IntacctEntityWriterTrait, LoggerAwareTrait;

    public function __construct(private IntacctApi $intacctApi, private IntacctCustomerWriter $customerWriter)
    {
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performCreate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->intacctApi->setAccount($account);

        try {
            // look for customer, create if it doesn't exist
            $customerMapping = AccountingCustomerMapping::findForCustomer($creditNote->customer(), $syncProfile->getIntegrationType());
            if (!$customerMapping) {
                $this->customerWriter->createIntacctCustomer($creditNote->customer(), $syncProfile, $account);
            }

            $adjustmentRequest = $this->buildArAdjustment($creditNote, $syncProfile);
            $recordNo = $this->createObjectWithEntityHandling($adjustmentRequest, $account, $syncProfile, $creditNote);
            $this->saveCreditNoteMapping($creditNote, $syncProfile->getIntegrationType(), $recordNo, AccountingCreditNoteMapping::SOURCE_INVOICED);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    protected function performUpdate(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void
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
    protected function performVoid(CreditNote $creditNote, Model $account, AccountingSyncProfile $syncProfile, AccountingCreditNoteMapping $creditNoteMapping): void
    {
        $this->intacctApi->setAccount($account);

        try {
            $deleteAdjustmentRequest = $this->buildArAdjustmentDelete($creditNote);
            $this->createObjectWithEntityHandling($deleteAdjustmentRequest, $account, $syncProfile, $creditNote);
        } catch (IntegrationApiException $e) {
            throw new SyncException($e->getMessage());
        }
    }

    //
    // Helpers
    //

    /**
     * Creates an ArAdjustmentCreate request
     * with data from a given CreditNote.
     */
    private function buildArAdjustment(CreditNote $creditNote, IntacctSyncProfile $syncProfile): ArAdjustmentCreate
    {
        $adjustment = new ArAdjustmentCreate();
        $adjustment->setCustomerId($creditNote->customer()->number);
        $adjustment->setAdjustmentNumber($creditNote->number);
        $adjustment->setDescription('Credit note # '.$creditNote->number.' imported from Invoiced (ID: '.$creditNote->id().')');

        // multi-currency
        $company = $creditNote->tenant();
        if ($company->features->has('multi_currency')) {
            $adjustment->setBaseCurrency(strtoupper($company->currency));
            $adjustment->setTransactionCurrency(strtoupper($creditNote->currency));
            $adjustment->setExchangeRateType('Intacct Daily Rate');
        }

        // dates
        $adjustment->setTransactionDate(Carbon::createFromTimestamp($creditNote->date));

        // line items
        $lineItems = $this->buildArAdjustmentLines($creditNote, $syncProfile);
        $adjustment->setLines($lineItems);

        return $adjustment;
    }

    /**
     * Creates a delete request for an A/R Adjustment
     * given its corresponding credit note.
     */
    private function buildArAdjustmentDelete(CreditNote $creditNote): ArAdjustmentDelete
    {
        $mapping = AccountingCreditNoteMapping::findOrFail($creditNote->id());
        $deleteRequest = new ArAdjustmentDelete();
        $deleteRequest->setRecordNo($mapping->accounting_id);

        return $deleteRequest;
    }

    /**
     * Creates an array of line items for an A/R Adjustment
     * using data from the given CreditNote.
     *
     * @return ArAdjustmentLineCreate[]
     */
    private function buildArAdjustmentLines(CreditNote $creditNote, IntacctSyncProfile $syncProfile): array
    {
        $customer = $creditNote->customer();

        // build from credit note items
        $lineItems = [];
        foreach ($creditNote->items() as $item) {
            $lineItems[] = $this->buildLineItem($item, $customer, $syncProfile);
        }

        // discounts
        if ($discount = $this->buildDiscountLine($creditNote, $syncProfile)) {
            $lineItems[] = $discount;
        }

        // tax
        if ($tax = $this->buildTaxLine($creditNote, $syncProfile)) {
            $lineItems[] = $tax;
        }

        return $lineItems;
    }

    private function buildLineItem(array $item, Customer $customer, IntacctSyncProfile $syncProfile): ArAdjustmentLineCreate
    {
        $lineItem = new ArAdjustmentLineCreate();

        // The amount must be negative to create a credit memo
        $lineItem->setTransactionAmount(-$item['amount']);
        $memo = $item['name'];
        if ($description = $item['description']) {
            $memo .= ': '.$description;
        }
        $lineItem->setMemo($memo);

        // set the line item dimensions
        $lineItem->setCustomerId($customer->number);

        if (property_exists($item['metadata'], 'intacct_glaccountno')) {
            $lineItem->setGlAccountNumber($item['metadata']->intacct_glaccountno);
        } elseif ($glAccountNumber = $syncProfile->item_account) {
            $lineItem->setGlAccountNumber($glAccountNumber);
        }

        if (property_exists($item['metadata'], 'intacct_offsetglaccountno')) {
            $lineItem->setOffsetGLAccountNumber($item['metadata']->intacct_offsetglaccountno);
        }

        if (property_exists($item['metadata'], 'intacct_allocation')) {
            $lineItem->setAllocationId($item['metadata']->intacct_allocation);
        }

        if (property_exists($item['metadata'], 'intacct_location')) {
            $lineItem->setLocationId($item['metadata']->intacct_location);
        } elseif ($locationId = $syncProfile->item_location_id) {
            $lineItem->setLocationId($locationId);
        }

        if (property_exists($item['metadata'], 'intacct_department')) {
            $lineItem->setDepartmentId($item['metadata']->intacct_department);
        } elseif ($departmentId = $syncProfile->item_department_id) {
            $lineItem->setDepartmentId($departmentId);
        }

        if (property_exists($item['metadata'], 'intacct_project')) {
            $lineItem->setProjectId($item['metadata']->intacct_project);
        }

        if (property_exists($item['metadata'], 'intacct_vendor')) {
            $lineItem->setVendorId($item['metadata']->intacct_vendor);
        }

        if (property_exists($item['metadata'], 'intacct_employee')) {
            $lineItem->setEmployeeId($item['metadata']->intacct_employee);
        }

        if (property_exists($item['metadata'], 'intacct_item')) {
            $lineItem->setItemId($item['metadata']->intacct_item);
        } elseif ($syncProfile->map_catalog_item_to_item_id && $item['catalog_item']) {
            $lineItem->setItemId($item['catalog_item']);
        }

        if (property_exists($item['metadata'], 'intacct_class')) {
            $lineItem->setClassId($item['metadata']->intacct_class);
        }

        if (property_exists($item['metadata'], 'intacct_contract')) {
            $lineItem->setContractId($item['metadata']->intacct_contract);
        }

        if (property_exists($item['metadata'], 'intacct_warehouse')) {
            $lineItem->setWarehouseId($item['metadata']->intacct_warehouse);
        }

        // set the custom fields
        if ($mapping = $syncProfile->line_item_custom_field_mapping) {
            $itemObject = json_decode((string) json_encode($item)); // convert from array to object
            $lineItem->setCustomFields($this->buildCustomFields($mapping, $itemObject));
        }

        return $lineItem;
    }

    private function buildDiscountLine(CreditNote $creditNote, IntacctSyncProfile $syncProfile): ?ArAdjustmentLineCreate
    {
        $discount = $this->getTotalDiscount($creditNote);
        if ($discount->isPositive()) {
            $lineItem = new ArAdjustmentLineCreate();
            $lineItem->setTransactionAmount($discount->negated()->toDecimal());
            $lineItem->setMemo('Discount');

            if ($glAccountNumber = $syncProfile->item_account) {
                $lineItem->setGlAccountNumber($glAccountNumber);
            }

            // set the line item dimensions
            $lineItem->setCustomerId($creditNote->customer()->number);

            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            return $lineItem;
        }

        return null;
    }

    private function buildTaxLine(CreditNote $creditNote, IntacctSyncProfile $syncProfile): ?ArAdjustmentLineCreate
    {
        $tax = $this->getTotalTax($creditNote);

        if ($tax->isPositive()) {
            $lineItem = new ArAdjustmentLineCreate();
            $lineItem->setTransactionAmount($tax->toDecimal());
            $lineItem->setMemo('Tax');

            if ($glAccountNumber = $syncProfile->item_account) {
                $lineItem->setGlAccountNumber($glAccountNumber);
            }

            // set the line item dimensions
            $lineItem->setCustomerId($creditNote->customer()->number);

            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            return $lineItem;
        }

        return null;
    }

    private function getIntacctEntity(Invoice|Payment|CreditNote $creditNote) : ?string
    {
        $metadata = $creditNote->metadata;
        if ($metadata !== null && property_exists($metadata, 'intacct_entity')) {
            return $metadata->intacct_entity;
        }

        $customer = $creditNote->customer();
        if ($customer === null) {
            return null;
        }

        return $this->customerWriter->getIntacctEntity($customer);
    }
}
