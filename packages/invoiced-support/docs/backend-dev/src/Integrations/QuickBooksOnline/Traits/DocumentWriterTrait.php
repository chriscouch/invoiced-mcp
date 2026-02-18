<?php

namespace App\Integrations\QuickBooksOnline\Traits;

use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\LineItem;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\Tax;
use App\Core\I18n\ValueObjects\Money;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\QuickBooksOnline\Models\QuickBooksOnlineSyncProfile;
use App\SalesTax\Models\TaxRate;

trait DocumentWriterTrait
{
    protected static array $defaultUSTaxCodes = ['TAX', 'NON'];
    protected static array $globalCountries = ['IN', 'GB', 'AU', 'CA']; // Countries which are allowed to use tax inclusion.

    /**
     * Builds base details for QBO documents.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildQBODocumentDetails(ReceivableDocument $receivableDocument, string $qboCustomerId, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        // format QBO doc number
        $docNumber = $this->formatDocumentNumber($receivableDocument, $syncProfile);

        // format date for QBO
        $txnDate = date('Y-m-d', $receivableDocument->date); // yyyy-mm-dd format

        // calculate tax inclusiveness
        $isTaxInclusive = $this->isTaxInclusive($receivableDocument);

        $details = [
            'DocNumber' => $docNumber,
            'PrivateNote' => substr((string) $receivableDocument->notes, 0, 4000),
            'TxnDate' => $txnDate,
            'CustomerRef' => [
                'value' => $qboCustomerId,
            ],
            'Line' => $this->buildQBOLineItems($receivableDocument, $isTaxInclusive, $syncProfile),
            'TxnTaxDetail' => $this->buildQBOTaxDetails($receivableDocument, $syncProfile),
            'GlobalTaxCalculation' => $isTaxInclusive ? 'TaxInclusive' : null,
        ];

        // set department ref
        $metadata = $receivableDocument->metadata;
        /** @var string|null $department */
        $department = $metadata->qbo_location ?? null;
        if ($department) {
            $details['DepartmentRef'] = [
                'value' => $this->getQBODepartmentId($department),
            ];
        }

        // QBO CurrencyRef and ExchangeRate
        $company = $receivableDocument->tenant();
        if ($company->features->has('multi_currency')) {
            $details['CurrencyRef'] = [
                'value' => $receivableDocument->currency,
            ];

            if ($receivableDocument->currency != $company->currency) {
                $currency = strtoupper($receivableDocument->currency);
                $details['ExchangeRate'] = $this->quickbooksApi->getExchangeRate($currency, $txnDate)->Rate;
            }
        }

        return $details;
    }

    /**
     * Formats Invoiced document line items into QBO line items.
     * Includes document discount line.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildQBOLineItems(ReceivableDocument $receivableDocument, bool $isTaxInclusive, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        $taxCodeCache = [];
        $classCache = [];
        $itemCache = [];
        $accountCache = [];

        $qboLineItems = [];
        foreach ($receivableDocument->items as $item) {
            // format line item name
            [$name, $catalogItem] = $this->formatItemName($item, $syncProfile);

            // build line
            $qboItem = [
                'Description' => $this->formatItemDescription($item),
                'DetailType' => 'SalesItemLineDetail',
                'Amount' => $item['amount'], // will be overwritten below if $isTaxInclusive
                'SalesItemLineDetail' => [
                    'Qty' => $item['quantity'],
                    'UnitPrice' => $item['unit_cost'], // will be overwritten below if $isTaxInclusive
                    'ItemRef' => [
                        'value' => $this->getQBOItemId($name, $catalogItem->id ?? null, $itemCache, $accountCache, $syncProfile), // link the item found/created
                    ],
                ],
            ];

            // calculate amounts with taxes included
            if ($isTaxInclusive) {
                $normalizedAmount = $this->normalizeQBOLineItemAmount($receivableDocument->currency, $item['amount'], $receivableDocument->taxes);
                $qboItem['Amount'] = $normalizedAmount;
                $qboItem['SalesItemLineDetail']['UnitPrice'] = Money::fromDecimal($receivableDocument->currency, $normalizedAmount / $item['quantity'])->toDecimal();
                $qboItem['SalesItemLineDetail']['TaxInclusiveAmt'] = Money::fromDecimal($receivableDocument->currency, $item['quantity'] * $item['unit_cost'])->toDecimal();
            }

            // set class ref
            $metadata = $item->metadata;
            $classMetadata = $metadata->qbo_class ?? null;
            if ($classMetadata) {
                $qboItem['SalesItemLineDetail']['ClassRef'] = [
                    'value' => $this->getQBOClassId($classMetadata, $classCache),
                ];
            }

            // set tax code ref
            $country = (string) $receivableDocument->tenant()->country;
            if ((!$catalogItem && $item['taxable']) || ($catalogItem && $catalogItem->taxable)) {
                if ('US' === $country) {
                    $taxCodeId = 'TAX';
                } else {
                    $taxRateName = $this->determineTaxCodeName(array_merge($item['taxes'], $receivableDocument->taxes), $syncProfile);
                    $taxCodeId = $this->getQBOTaxCodeId($taxRateName, $taxCodeCache);
                }

                $qboItem['SalesItemLineDetail']['TaxCodeRef'] = [
                    'value' => $taxCodeId,
                ];
            } elseif (in_array($country, self::$globalCountries)) {
                throw new SyncException('QuickBooks Online requires taxable line items.');
            }

            $qboLineItems[] = $qboItem;
        }

        // build discount line
        if ($discountLine = $this->buildQBODiscountLine($receivableDocument, $syncProfile)) {
            $qboLineItems[] = $discountLine;
        }

        return $qboLineItems;
    }

    /**
     * Determines the tax code name to use given the integration settings
     * and the list of applied taxes. If tax rate matching is enabled then
     * this uses the name of the first tax rate in a list of applied taxes
     * or else the default tax code.
     * If tax rate matching is disabled then this returns the default tax code.
     *
     * @throws SyncException
     */
    private function determineTaxCodeName(array $taxes, QuickBooksOnlineSyncProfile $syncProfile): string
    {
        if ($syncProfile->match_tax_rates) {
            foreach ($taxes as $tax) {
                if (isset($tax['tax_rate'])) {
                    return $tax['tax_rate']['name'];
                } else if ($tax instanceof Tax && $tax->rate_id) {
                    $rate = TaxRate::find($tax->rate_id);
                    if (!$rate) {
                        throw new SyncException('Unable to determine QuickBooks Online tax code.');
                    }
                    return $rate->name;
                }
            }
        }

        if ($taxCodeName = $syncProfile->tax_code) {
            return $taxCodeName;
        }

        throw new SyncException('Unable to determine QuickBooks Online tax code.');
    }

    /**
     * Builds a QBO discount line based on the discounts applied to
     * the individual line items and document as a whole.
     *
     * @throws IntegrationApiException
     */
    public function buildQBODiscountLine(ReceivableDocument $receivableDocument, QuickBooksOnlineSyncProfile $syncProfile): ?array
    {
        $currency = $receivableDocument->currency;
        $discountReducer = fn (Money $carry, Discount $discount) => $carry->add(Money::fromDecimal($currency, $discount->amount));

        // calculate total discount
        $totalDiscount = array_reduce($receivableDocument->discounts, $discountReducer, Money::fromDecimal($currency, 0));
        foreach ($receivableDocument->items as $item) {
            $itemDiscount = array_reduce($item->discounts, $discountReducer, Money::fromDecimal($currency, 0));
            $totalDiscount = $totalDiscount->add($itemDiscount);
        }

        if ($totalDiscount->isZero()) {
            return null;
        }

        return [
            'DetailType' => 'DiscountLineDetail',
            'Amount' => $totalDiscount->toDecimal(),
            'DiscountLineDetail' => [
                'DiscountAccountRef' => [
                    'value' => $this->getQBODiscountAccountId($syncProfile),
                ],
            ],
        ];
    }

    /**
     * Builds QBO document tax details for create/update request.
     *
     * @throws IntegrationApiException|SyncException
     */
    public function buildQBOTaxDetails(ReceivableDocument $receivableDocument, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        $currency = $receivableDocument->currency;
        $taxReducer = fn (Money $carry, Tax $tax) => $carry->add(Money::fromDecimal($currency, $tax->amount));

        // calculate total tax
        $totalTax = array_reduce($receivableDocument->taxes, $taxReducer, Money::fromDecimal($currency, 0));
        foreach ($receivableDocument->items as $item) {
            $itemTax = array_reduce($item->taxes, $taxReducer, Money::fromDecimal($currency, 0));
            $totalTax = $totalTax->add($itemTax);
        }

        $taxCodeName = $this->determineTaxCodeName($receivableDocument->taxes, $syncProfile);

        // U.S. QuickBooks Online accounts use a different tax line data structure
        if ('US' === $receivableDocument->tenant()->country) {
            // The tax codes TAX and NON cannot be queried. The name is the ID.
            if (in_array($taxCodeName, self::$defaultUSTaxCodes)) {
                $taxCodeId = $taxCodeName;
            } else {
                $taxCodeId = $this->getQBOTaxCodeId($taxCodeName);
            }

            return [
                'TotalTax' => $totalTax->toDecimal(),
                'TxnTaxCodeRef' => [
                    'value' => $taxCodeId,
                ],
            ];
        }

        // find tax details
        $taxCode = $this->quickbooksApi->getTaxCode($taxCodeName);
        if (!$taxCode) {
            throw new SyncException('Could not retrieve tax code: '.$taxCodeName);
        }

        if (0 == count($taxCode->SalesTaxRateList->TaxRateDetail ?? [])) {
            throw new SyncException('Default tax code must have a sales tax rate. Either set a new default taxc ode or add a sales tax rate in QuickBooks.');
        }

        return [
            'TotalTax' => $totalTax->toDecimal(),
            'TaxLine' => [
                [
                    'DetailType' => 'TaxLineDetail',
                    'Amount' => $totalTax->toDecimal(),
                    'TaxLineDetail' => [
                        'NetAmountTaxable' => $receivableDocument->subtotal,
                        'TaxRateRef' => [
                            'value' => $taxCode->SalesTaxRateList->TaxRateDetail[0]->TaxRateRef->value,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Formats a document's number to one suitable for QBO.
     */
    protected function formatDocumentNumber(ReceivableDocument $receivableDocument, QuickBooksOnlineSyncProfile $syncProfile): string
    {
        $docNumber = $syncProfile->namespace_invoices
            ? 'INVD-'.$receivableDocument->number
            : $receivableDocument->number;

        return substr($docNumber, 0, 21);
    }

    /**
     * Formats an Invoiced line item's name to one
     * suitable for QBO.
     *
     * Parses name from line item and formats it by
     * - removing characters that aren't alphanumeric or equal to '-' or ' ',
     * - trimming it to 100 characters,
     * - appending 'INVD' if necessary.
     */
    private function formatItemName(LineItem $lineItem, QuickBooksOnlineSyncProfile $syncProfile): array
    {
        $name = 'INVOICED ITEM'; // used if item has no name.

        // if item is catalog item, use the catalog item name.
        $item = $lineItem->item();
        if ($item) {
            $name = $item->name;
        } elseif (strlen($lineItem['name']) > 0) {
            $name = $lineItem['name'];
        }

        // remove chars that aren't alphanumerics, '-' or ' '
        $normalized = preg_replace("/[^0-9a-zA-Z \-]/", '', $name);

        // trim and append INVD if necessary
        $normalized = trim($normalized);
        if ($syncProfile->namespace_items) {
            $normalized = 'INVD '.$normalized;
        }

        return [substr($normalized, 0, 100), $item];
    }

    /**
     * Formats the description of an Invoiced line item
     * to one suitable for QBO.
     */
    private function formatItemDescription(LineItem $item): string
    {
        // format the description
        $description = trim((string) $item['description']);
        if ($item['period_start'] > 0 && $item['period_end'] > 0) {
            $periodStart = date('Y-m-d', $item['period_start']);
            $periodEnd = date('Y-m-d', $item['period_end']);
            $description = "Billing Period: $periodStart - $periodEnd\n".$description;
        }

        return substr($description, 0, 4000);
    }

    //
    // QBO API Helpers
    //

    /**
     * Gets the ID of a QuickBooks department by name.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getQBODepartmentId(string $department): string
    {
        $qboDepartment = $this->quickbooksApi->getDepartment($department);
        if (!$qboDepartment) {
            throw new SyncException('Could not find Department: '.$department);
        }

        return (string) $qboDepartment->Id;
    }

    /**
     * Gets the ID of a QuickBooks class by name.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getQBOClassId(string $class, array &$cache): string
    {
        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $qboClass = $this->quickbooksApi->getClass($class);
        if (!$qboClass) {
            throw new SyncException('Could not find Class: '.$class);
        }

        $classId = (string) $qboClass->Id;
        $cache[$class] = $classId;

        return $classId;
    }

    /**
     * Attempts to find a QBO income account id using information
     * from the customer's QBO sync profile.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getQBOIncomeAccountId(array &$cache, QuickBooksOnlineSyncProfile $syncProfile): string
    {
        $accountName = $syncProfile->undeposited_funds_account;
        if (!$accountName) {
            throw new SyncException('QuickBooks Online income account is missing.');
        }

        if (isset($cache[$accountName])) {
            return $cache[$accountName];
        }

        $account = $this->quickbooksApi->getAccountByName($accountName);
        if (!$account) {
            throw new SyncException('Could not find QuickBooks Online income account: '.$accountName);
        }

        $accountId = $account->Id;
        $cache[$accountName] = $accountId;

        return $accountId;
    }

    /**
     * Attempts to find a QBO discount account id using information
     * from the customer's QBO sync profile.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getQBODiscountAccountId(QuickBooksOnlineSyncProfile $syncProfile): string
    {
        $accountName = $syncProfile->discount_account;
        if (!$accountName) {
            throw new SyncException('QuickBooks Online discount account is missing.');
        }

        $account = $this->quickbooksApi->getAccountByName($accountName);
        if (!$account) {
            throw new SyncException('Could not find QuickBooks Online discount account: '.$accountName);
        }

        return (string) $account->Id;
    }

    /**
     * Attempts to find a QBO tax code id using information
     * from the customer's QBO sync profile.
     *
     * @throws IntegrationApiException|SyncException
     */
    private function getQBOTaxCodeId(string $taxCodeName, ?array &$cache = null): string
    {
        if (isset($cache[$taxCodeName])) {
            return $cache[$taxCodeName];
        }

        // look up tax code on QBO
        $taxCode = $this->quickbooksApi->getTaxCode($taxCodeName);
        if (!$taxCode) {
            throw new SyncException('Could not find QuickBooks Online tax code: '.$taxCodeName);
        }

        $taxCodeId = $taxCode->Id;
        if (isset($cache)) {
            $cache[$taxCodeName] = $taxCodeId;
        }

        return $taxCodeId;
    }

    /**
     * Attempts to find an Id of an existing QBO item.
     * If one is not found, one is created with the
     * sku provided as an argument and the QBO income
     * account id.
     *
     * @throws IntegrationApiException
     */
    private function getQBOItemId(string $name, ?string $sku, array &$cache, array &$accountCache, QuickBooksOnlineSyncProfile $syncProfile): string
    {
        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $name = substr($name, 0, 100);
        $foundQBOItem = $this->quickbooksApi->getItemByName($name);
        if (!$foundQBOItem) {
            $foundQBOItem = $this->quickbooksApi->createItem([
                'Name' => $name,
                'Type' => 'Service',
                'Sku' => substr((string) $sku, 0, 100),
                'IncomeAccountRef' => [
                    'value' => $this->getQBOIncomeAccountId($accountCache, $syncProfile),
                ],
            ]);
        }

        $itemId = (string) $foundQBOItem->Id;
        $cache[$name] = $itemId;

        return $itemId;
    }

    //
    // Tax helpers
    //

    /**
     * Normalizes line item amount based on an invoices
     * taxes. (Not line item taxes).
     *
     * @throws SyncException
     */
    public function normalizeQBOLineItemAmount(string $currency, float $amount, array $taxes): float
    {
        $lineAmount = Money::fromDecimal($currency, $amount);
        $totalTax = new Money($currency, 0);
        foreach ($taxes as $tax) {
            $rate = TaxRate::find($tax['rate_id']);

            if (!$rate) {
                throw new SyncException('Tax rate cannot be null.');
            } elseif (!$rate->is_percent) {
                throw new SyncException('Tax rate must be a percentage.');
            }

            $taxRate = $rate->value;
            $inclusiveAmount = $amount / (1 + $taxRate / 100);
            $roundedUp = Money::fromDecimal($currency, floor($inclusiveAmount * 100) / 100);
            $totalTax = $totalTax->add($lineAmount)->subtract($roundedUp);
        }

        return $lineAmount->subtract($totalTax)->toDecimal();
    }

    /**
     * Calculates if document is tax inclusive.
     *
     * @throws SyncException if there is a mismatch of tax inclusive line items
     */
    public function isTaxInclusive(ReceivableDocument $receivableDocument): bool
    {
        // count number of tax inclusive line items
        $taxInclusiveLineCounter = 0;
        foreach ($receivableDocument->items as $item) {
            if ($this->areTaxesInclusive($item->taxes)) {
                ++$taxInclusiveLineCounter;
            }
        }

        if ($taxInclusiveLineCounter > 0) {
            // check country for tax inclusiveness
            $country = $receivableDocument->tenant()->country;
            if (!in_array($country, self::$globalCountries)) {
                throw new SyncException('Syncing invoices with tax inclusive line items is not supported.');
            }

            // ensure all or no line items are tax inclusive
            if ($taxInclusiveLineCounter != count($receivableDocument->items)) {
                throw new SyncException('Line items have mismatched tax inclusive values.');
            }
        }

        // check if line item taxes and document taxes are inclusive
        $lineItemTaxesAreInclusive = $taxInclusiveLineCounter > 0;
        $documentTaxesAreInclusive = $this->areTaxesInclusive($receivableDocument->taxes);

        // check line item v. document tax inclusion mismatch
        if ($lineItemTaxesAreInclusive != $documentTaxesAreInclusive) {
            if (count($receivableDocument->taxes) < 1) {
                return $lineItemTaxesAreInclusive;
            }

            throw new SyncException('Tax inclusive line items does not match tax inclusive on subtotal.');
        }

        return $lineItemTaxesAreInclusive;
    }

    /**
     * Returns whether or not all tax objects in an
     * array are tax inclusive.
     */
    public function areTaxesInclusive(array $taxes): bool
    {
        $reducer = function (bool $carry, Tax $tax) {
            $rate = TaxRate::find($tax->rate_id);

            return $carry && ($rate->inclusive ?? false);
        };

        return array_reduce($taxes, $reducer, count($taxes) > 0);
    }
}
