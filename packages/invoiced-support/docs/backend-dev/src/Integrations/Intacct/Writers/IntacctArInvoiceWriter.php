<?php

namespace App\Integrations\Intacct\Writers;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Libs\ImportHelper;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Carbon\Carbon;
use Intacct\Functions\AbstractFunction;
use Intacct\Functions\AccountsReceivable\InvoiceCreate;
use Intacct\Functions\AccountsReceivable\InvoiceLineCreate;
use Intacct\Functions\AccountsReceivable\InvoiceReverse;

/**
 * Posts invoices to Intacct using the A/R module.
 */
class IntacctArInvoiceWriter extends AbstractIntacctInvoiceWriter
{
    public function buildCreateRequest(Invoice $invoice, IntacctSyncProfile $syncProfile): AbstractFunction
    {
        $intacctInvoice = new InvoiceCreate();

        $customer = $invoice->customer();
        $intacctInvoice->setCustomerId($customer->number);

        // Intacct seems to override the invoice # based on the settings
        // so we are also going to also set it in the reference number
        $intacctInvoice->setInvoiceNumber($invoice->number);
        if ($poNumber = $invoice->purchase_order) {
            $intacctInvoice->setReferenceNumber($poNumber);
        }
        $intacctInvoice->setDescription('Invoice # '.$invoice->number.' imported from Invoiced (ID: '.$invoice->id().')');

        // multi-currency
        $company = $invoice->tenant();
        if ($company->features->has('multi_currency')) {
            $intacctInvoice->setBaseCurrency(strtoupper($company->currency));
            $intacctInvoice->setTransactionCurrency(strtoupper($invoice->currency));
            $intacctInvoice->setExchangeRateType('Intacct Daily Rate');
        }

        // dates
        $date = Carbon::createFromTimestamp($invoice->date);
        $intacctInvoice->setTransactionDate($date);

        if ($dueDate = $invoice->due_date) {
            $dueDate = Carbon::createFromTimestamp($dueDate);
            $intacctInvoice->setDueDate($dueDate);
        } else {
            $intacctInvoice->setDueDate($date);
        }

        // custom fields
        if ($mapping = $syncProfile->invoice_custom_field_mapping) {
            $intacctInvoice->setCustomFields($this->buildCustomFields($mapping, $invoice));
        }

        // line items
        $lineItems = [];
        foreach ($invoice->items() as $item) {
            $lineItems[] = $this->buildLineItem($item, $customer, $syncProfile);
        }

        // discounts
        $totalDiscount = $this->getTotalDiscount($invoice);

        if ($totalDiscount->isPositive()) {
            $lineItem = new InvoiceLineCreate();
            $lineItem->setTransactionAmount($totalDiscount->negated()->toDecimal());
            $lineItem->setMemo('Discount');

            if ($glAccountNumber = $syncProfile->item_account) {
                $lineItem->setGlAccountNumber($glAccountNumber);
            }

            // set the line item dimensions
            $lineItem->setCustomerId($customer->number);

            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            $lineItems[] = $lineItem;
        }

        // tax
        $totalTax = $this->getTotalTax($invoice);

        if ($totalTax->isPositive()) {
            $lineItem = new InvoiceLineCreate();
            $lineItem->setTransactionAmount($totalTax->toDecimal());
            $lineItem->setMemo('Tax');

            if ($glAccountNumber = $syncProfile->item_account) {
                $lineItem->setGlAccountNumber($glAccountNumber);
            }

            // set the line item dimensions
            $lineItem->setCustomerId($customer->number);

            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            $lineItems[] = $lineItem;
        }

        $intacctInvoice->setLines($lineItems);

        return $intacctInvoice;
    }

    private function buildLineItem(array $item, Customer $customer, IntacctSyncProfile $syncProfile): InvoiceLineCreate
    {
        $lineItem = new InvoiceLineCreate();
        $amount = $item['amount'];
        $lineItem->setTransactionAmount($amount);
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

        if (property_exists($item['metadata'], 'intacct_deferredrevaccount')) {
            $lineItem->setDeferredRevGlAccountNo($item['metadata']->intacct_deferredrevaccount);
        }

        try {
            if (property_exists($item['metadata'], 'intacct_revrecstartdate')) {
                if ($date = ImportHelper::parseDate($item['metadata']->intacct_revrecstartdate)) {
                    $lineItem->setRevRecStartDate($date->setTime(6, 0)->toMutable());
                }
            }
            if (property_exists($item['metadata'], 'intacct_revrecenddate')) {
                if ($date = ImportHelper::parseDate($item['metadata']->intacct_revrecenddate)) {
                    $lineItem->setRevRecEndDate($date->setTime(6, 0)->toMutable());
                }
            }
        } catch (ValidationException $e) {
            throw new SyncException($e->getMessage());
        }

        if (property_exists($item['metadata'], 'intacct_revrectemplate')) {
            $lineItem->setRevRecTemplateId($item['metadata']->intacct_revrectemplate);
        }

        // set the custom fields
        if ($mapping = $syncProfile->line_item_custom_field_mapping) {
            $itemObject = json_decode((string) json_encode($item)); // convert from array to object
            $lineItem->setCustomFields($this->buildCustomFields($mapping, $itemObject));
        }

        return $lineItem;
    }

    protected function buildVoidRequest(Invoice $invoice, AccountingInvoiceMapping $mapping): AbstractFunction
    {
        $intacctInvoice = new InvoiceReverse();
        $intacctInvoice->setRecordNo($mapping->accounting_id);
        $date = Carbon::createFromTimestamp((int) $invoice->date_voided);
        $intacctInvoice->setReverseDate($date);
        $intacctInvoice->setMemo('Voided on Invoiced');

        return $intacctInvoice;
    }
}
