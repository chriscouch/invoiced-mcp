<?php

namespace App\Integrations\Xero\Traits;

use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\AccountsReceivable\Exception\InvoiceCalculationException;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\ValueObjects\CalculatedInvoice;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Xero\Models\XeroSyncProfile;
use Carbon\CarbonImmutable;

trait DocumentWriterTrait
{
    /**
     * @throws SyncException
     */
    protected function buildDocumentRequest(ReceivableDocument $document, string $xeroCustomerId, XeroSyncProfile $syncProfile): ?array
    {
        // Calculate the invoice to get total tax and total discounts
        // across subtotal and line items.
        try {
            $calculatedInvoice = $document->getCalculatedInvoice()->denormalize();
        } catch (InvoiceCalculationException $e) {
            throw new SyncException($e->getMessage());
        }

        $taxType = null;
        // In these tax modes we determine the tax type that is to be added to all line items
        if ('match_tax_rate' == $syncProfile->tax_mode) {
            $taxType = $this->getTaxType($calculatedInvoice);
        }

        $lineItems = [];
        foreach ($document->items() as $lineItem) {
            $lineItems[] = $this->buildLineItemRequest($document, $lineItem, $taxType, $syncProfile);
        }

        if ($discountLine = $this->buildDiscountLineItem($calculatedInvoice, $taxType, $syncProfile)) {
            $lineItems[] = $discountLine;
        }

        // This tax mode will add sales tax as a line item
        if ('tax_line_item' == $syncProfile->tax_mode) {
            $lineItems = array_merge($lineItems, $this->buildTaxLineItems($calculatedInvoice, $syncProfile));
        }

        $request = [
            'Status' => 'AUTHORISED',
            'Contact' => [
                'ContactID' => $xeroCustomerId,
            ],
            'CurrencyCode' => strtoupper($document->currency),
            'Reference' => $document->purchase_order,
            'Date' => CarbonImmutable::createFromTimestamp($document->date)->toDateString(),
            'LineItems' => $lineItems,
        ];

        if ('Convenience Fee' == $document->name) {
            // This should always be NoTax for a convenience fee invoice
            $request['LineAmountTypes'] = 'NoTax';
        } elseif ('tax_line_item' == $syncProfile->tax_mode) {
            // Sales tax is added as a line item therefore we must tell
            // Xero not to assess it anywhere else.
            $request['LineAmountTypes'] = 'NoTax';
        } elseif (in_array($syncProfile->tax_mode, ['inherit', 'match_tax_rate'])) {
            $request['LineAmountTypes'] = 'Exclusive';
        }

        // add in custom line item overrides
        if (property_exists($document->metadata, 'xero_lineamounttypes')) {
            $request['LineAmountTypes'] = $document->metadata->xero_lineamounttypes;
        }

        return $request;
    }

    protected function buildLineItemRequest(ReceivableDocument $document, array $lineItem, ?string $taxType, XeroSyncProfile $syncProfile): array
    {
        // get AccountCode for line item
        if ('Convenience Fee' == $document->name) {
            if (!$syncProfile->convenience_fee_account) {
                throw new SyncException('Convenience fee account is not configured.');
            }
            $accountCode = $syncProfile->convenience_fee_account;
        } else {
            if (!$syncProfile->item_account) {
                // the Dashboard refers to the 'item_account' property as the 'Sales Account'
                throw new SyncException('Sales account is not configured.');
            }
            $accountCode = $syncProfile->item_account;
        }

        $xeroLine = [
            'Description' => trim($lineItem['name'].' '.$lineItem['description']),
            'AccountCode' => $accountCode,
            'Tracking' => [],
        ];

        if (!$xeroLine['Description']) {
            $xeroLine['Description'] = 'No Description';
        }

        if ($syncProfile->send_item_code) {
            if ($lineItem['catalog_item']) {
                $xeroLine['ItemCode'] = $lineItem['catalog_item'];
            }
        }

        $xeroLine['Quantity'] = $lineItem['quantity'];
        $xeroLine['UnitAmount'] = $lineItem['unit_cost'];
        $xeroLine['LineAmount'] = $lineItem['amount'];

        if ($taxType) {
            $xeroLine['TaxType'] = $taxType;
        }

        // add in custom line item overrides
        if (property_exists($lineItem['metadata'], 'xero_accountcode')) {
            $xeroLine['AccountCode'] = $lineItem['metadata']->xero_accountcode;
        }
        if (property_exists($lineItem['metadata'], 'xero_itemcode')) {
            $xeroLine['ItemCode'] = $lineItem['metadata']->xero_itemcode;
        }
        if (property_exists($lineItem['metadata'], 'xero_taxtype')) {
            $xeroLine['TaxType'] = $lineItem['metadata']->xero_taxtype;
        }
        if (property_exists($lineItem['metadata'], 'xero_trackingname1') && property_exists($lineItem['metadata'], 'xero_trackingoption1')) {
            $xeroLine['Tracking'][] = [
                'Name' => $lineItem['metadata']->xero_trackingname1,
                'Option' => $lineItem['metadata']->xero_trackingoption1,
            ];
        }
        if (property_exists($lineItem['metadata'], 'xero_trackingname2') && property_exists($lineItem['metadata'], 'xero_trackingoption2')) {
            $xeroLine['Tracking'][] = [
                'Name' => $lineItem['metadata']->xero_trackingname2,
                'Option' => $lineItem['metadata']->xero_trackingoption2,
            ];
        }

        if (!count($xeroLine['Tracking'])) {
            unset($xeroLine['Tracking']);
        }

        return $xeroLine;
    }

    protected function buildDiscountLineItem(CalculatedInvoice $document, ?string $taxType, XeroSyncProfile $syncProfile): ?array
    {
        if ($document->totalDiscounts > 0) {
            if (!$syncProfile->discount_account) {
                throw new SyncException('Discount account is not configured.');
            }

            $lineItem = [
                'AccountCode' => $syncProfile->discount_account,
                'Description' => 'Total Discount',
                'Quantity' => 1,
                'UnitAmount' => -$document->totalDiscounts,
            ];

            if ($taxType) {
                $lineItem['TaxType'] = $taxType;
            }

            return $lineItem;
        }

        return null;
    }

    protected function buildTaxLineItems(CalculatedInvoice $document, XeroSyncProfile $syncProfile): array
    {
        $taxLines = [];

        if ($document->totalTaxes > 0) {
            if (!$syncProfile->sales_tax_account) {
                throw new SyncException('Sales tax account is not configured.');
            }

            $taxLines[] = [
                'AccountCode' => $syncProfile->sales_tax_account,
                'Description' => 'Total Sales Tax',
                'Quantity' => 1,
                'UnitAmount' => $document->totalTaxes,
            ];
        }

        // add an adjustment if tax inclusive pricing is used
        // or else the total will be off because Xero is calculating
        // the invoice using tax exclusive pricing
        foreach ($document->taxes as $appliedRate) {
            if (isset($appliedRate['tax_rate']) && $appliedRate['tax_rate']['inclusive']) {
                if (!$syncProfile->item_account) {
                    // the Dashboard refers to the 'item_account' property as the 'Sales Account'
                    throw new SyncException('Sales account is not configured.');
                }

                $taxLines[] = [
                    'AccountCode' => $syncProfile->item_account,
                    'Description' => 'Tax Inclusive Pricing Adjustment',
                    'Quantity' => 1,
                    'UnitAmount' => -$document->totalTaxes,
                ];
                break;
            }
        }

        return $taxLines;
    }

    /**
     * Looks up the tax type to use on line items when tax_mode=match_tax_rate.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getTaxType(CalculatedInvoice $calculatedInvoice): string
    {
        foreach ($calculatedInvoice->taxes as $appliedRate) {
            if (isset($appliedRate['tax_rate'])) {
                // look up from xero
                $taxRates = $this->xeroApi->getMany('TaxRates', [
                    'where' => 'Name=="'.addslashes($appliedRate['tax_rate']['name']).'"',
                ]);
                if (1 == count($taxRates)) {
                    return $taxRates[0]->TaxType;
                }
            }
        }

        throw new SyncException('Could not find a matching tax rate on Xero');
    }
}
