<?php

namespace App\Integrations\Intacct\Writers;

use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\AccountsReceivable\Models\Invoice;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use Carbon\Carbon;
use Intacct\Functions\AbstractFunction;
use Intacct\Functions\InventoryControl\TransactionSubtotalCreate;
use Intacct\Functions\OrderEntry\OrderEntryTransactionCreate;
use Intacct\Functions\OrderEntry\OrderEntryTransactionDelete;
use Intacct\Functions\OrderEntry\OrderEntryTransactionLineCreate;

/**
 * Posts invoices to Intacct using the Order Entry module.
 */
class IntacctOrderEntryInvoiceWriter extends AbstractIntacctInvoiceWriter
{
    public function buildCreateRequest(Invoice $invoice, IntacctSyncProfile $syncProfile): AbstractFunction
    {
        // TODO
        // This operation returns the order entry ID in the format Sales Invoice-INV-2368
        // In order to record payments we need the PRRECORDKEY value

        $intacctInvoice = new OrderEntryTransactionCreate();

        $customer = $invoice->customer();
        $intacctInvoice->setCustomerId($customer->number);

        // TODO setting
        $intacctInvoice->setTransactionDefinition('Sales Invoice');
        $intacctInvoice->setDocumentNumber($invoice->number);
        if ($poNumber = $invoice->purchase_order) {
            $intacctInvoice->setReferenceNumber($poNumber);
        }

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

        // line items
        $lineItems = [];
        foreach ($invoice->items() as $item) {
            $lineItem = new OrderEntryTransactionLineCreate();
            $lineItem->setQuantity($item['quantity']);
            $lineItem->setPrice($item['unit_cost']);

            if ($name = $item['name']) {
                $lineItem->setItemDescription($name);
            }

            if ($description = $item['description']) {
                $lineItem->setMemo($description);
            }

            // set the line item dimensions
            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            if ($item['catalog_item']) {
                $lineItem->setItemId($item['catalog_item']);
            } else {
                // TODO
                $lineItem->setItemId('Consulting');
            }

            // TODO
            $lineItem->setUnit('Hour');

            $lineItems[] = $lineItem;
        }
        $intacctInvoice->setLines($lineItems);

        // tax
        $subtotalLines = [];
        $totalTax = $this->getTotalTax($invoice);
        if ($totalTax->isPositive()) {
            $lineItem = new TransactionSubtotalCreate();
            $lineItem->setTotal((string) $totalTax->toDecimal());
            $lineItem->setAbsoluteValue((string) $totalTax->toDecimal());
            // sales tax requires a percent value
            $percent = round($totalTax->toDecimal() / $invoice->subtotal, 4);
            $lineItem->setPercentageValue((string) $percent);
            // this probably needs to be configurable
            $lineItem->setDescription('Sales Tax');

            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            $subtotalLines[] = $lineItem;
        }

        // discounts
        $totalDiscount = $this->getTotalDiscount($invoice);
        if ($totalDiscount->isPositive()) {
            $lineItem = new TransactionSubtotalCreate();
            $lineItem->setTotal((string) $totalDiscount->negated()->toDecimal());
            $lineItem->setAbsoluteValue((string) $totalDiscount->toDecimal());
            $lineItem->setDescription('Discount');

            if ($locationId = $syncProfile->item_location_id) {
                $lineItem->setLocationId($locationId);
            }

            if ($departmentId = $syncProfile->item_department_id) {
                $lineItem->setDepartmentId($departmentId);
            }

            $subtotalLines[] = $lineItem;
        }

        $intacctInvoice->setSubtotals($subtotalLines);

        return $intacctInvoice;
    }

    protected function buildVoidRequest(Invoice $invoice, AccountingInvoiceMapping $mapping): AbstractFunction
    {
        $request = new OrderEntryTransactionDelete();
        // TODO setting
        $request->setTransactionDefinition('Sales Invoice');
        $request->setDocumentId($mapping->accounting_id);
        $request->setMessage('Voided on Invoiced');

        return $request;
    }
}
