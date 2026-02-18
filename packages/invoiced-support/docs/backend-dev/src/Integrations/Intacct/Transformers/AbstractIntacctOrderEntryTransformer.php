<?php

namespace App\Integrations\Intacct\Transformers;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\LineItem;
use App\Core\I18n\ValueObjects\Money;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Model;
use App\Imports\Libs\ImportHelper;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\AccountingSync\ValueObjects\AccountingCustomer;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Libs\IntacctMapper;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\PaymentPlans\Models\PaymentPlanInstallment;
use SimpleXMLElement;

abstract class AbstractIntacctOrderEntryTransformer implements TransformerInterface
{
    protected IntacctMapper $mapper;

    private ?object $customMapping = null;
    private ?object $customItemMapping = null;
    private ?string $locationIdFilter = null;
    private bool $hasMultiCurrency;
    private string $defaultCurrency;
    private ?object $customPaymentPlanMapping = null;
    private ?object $paymentPlanImportSettings = null;
    private string $documentType;
    private bool $importDrafts = false;
    private bool $billToCustomerSource = false;

    public function __construct(
        protected TenantContext $tenant,
    ) {
        $this->mapper = new IntacctMapper();
    }

    /**
     * MUST be called BEFORE initialize().
     *
     * @param string $documentType document type, i.e. Sales Invoice
     */
    public function setDocumentType(string $documentType): void
    {
        $this->documentType = $documentType;
    }

    /**
     * @param IntacctAccount     $account
     * @param IntacctSyncProfile $syncProfile
     */
    public function initialize(Model $account, AccountingSyncProfile $syncProfile): void
    {
        $this->hasMultiCurrency = $account->tenant()->features->has('multi_currency');
        $this->defaultCurrency = $account->tenant()->currency;

        // add in custom mapping and query settings
        $this->billToCustomerSource = IntacctSyncProfile::CUSTOMER_IMPORT_TYPE_BILL_TO == $syncProfile->customer_import_type;
        $this->paymentPlanImportSettings = $syncProfile->payment_plan_import_settings;

        if ($mapping = $syncProfile->invoice_import_mapping) {
            $this->customMapping = $this->mapper->parseDocumentFieldMapping($mapping, $this->documentType);
        }

        if ($mapping = $syncProfile->line_item_import_mapping) {
            $this->customItemMapping = $this->mapper->parseDocumentFieldMapping($mapping, $this->documentType);
        }

        if ($this->paymentPlanImportSettings) {
            $cppDocType = $this->paymentPlanImportSettings->document_type;
            $mapping = $this->paymentPlanImportSettings->mapping;

            if ($this->documentType == $cppDocType) {
                $this->customPaymentPlanMapping = $this->mapper->parseDocumentFieldMapping($mapping, $this->documentType);
            }
        }

        $this->locationIdFilter = $syncProfile->invoice_location_id_filter;

        $this->importDrafts = $syncProfile->read_invoices_as_drafts;
    }

    protected function buildCustomer(SimpleXMLElement $intacctInvoice): ?AccountingCustomer
    {
        // are we importing from a bill to contact or an A/R customer?
        if ($this->billToCustomerSource) {
            $customer = $this->buildCustomerFromIntacctBillTo($intacctInvoice);
        } else {
            $customer = $this->buildCustomerFromIntacctArCustomer($intacctInvoice);
        }

        return $customer;
    }

    protected function buildDocumentValues(SimpleXMLElement $intacctInvoice): ?array
    {
        // ignore order entry transactions in a draft state
        $state = (string) $intacctInvoice->{'STATE'};
        if ('Draft' == $state) {
            return null;
        }

        $record = [
            // The accounting system should have already calculated any tax
            'calculate_taxes' => false,
            'metadata' => [
                'intacct_document_type' => $this->documentType,
            ],
        ];

        // invoice #
        if ($number = (string) $intacctInvoice->{'DOCNO'}) {
            $record['number'] = trim($number);
        }

        // currency
        if ($currency = (string) $intacctInvoice->{'CURRENCY'}) {
            $record['currency'] = strtolower($currency);
        }

        // enable draft mode for new documents
        if ($this->importDrafts) {
            $record['draft'] = true;
        }

        // date
        if ($date = (string) $intacctInvoice->{'WHENPOSTED'}) {
            $record['date'] = $this->mapper->parseIsoDate($date);
        }

        // message
        if ($notes = (string) $intacctInvoice->{'MESSAGE'}) {
            $record['notes'] = $notes;
        }

        // po number
        if ($poNumber = (string) $intacctInvoice->{'PONUMBER'}) {
            $record['metadata']['intacct_purchase_order'] = $poNumber;
            $record['purchase_order'] = substr($poNumber, 0, 32);
        }

        // contract ID
        if ($contractId = (string) $intacctInvoice->{'CONTRACTID'}) {
            $record['metadata']['intacct_contract_id'] = $contractId;
        }

        // build the document line items
        $record = $this->buildLineItems($intacctInvoice, $record);

        // add the subtotal items into the document
        $record = $this->buildSubtotalLines($intacctInvoice, $record);
        if (!$record) {
            return null;
        }

        // DEBUG: look for invoices with no line items
        if (0 === count($record['items'])) {
            throw new TransformException('Invoice is missing line items!');
        }

        // ship to address
        if ($shipToName = (string) $intacctInvoice->{'SHIPTO'}->{'PRINTAS'}) {
            $intacctShipTo = $intacctInvoice->{'SHIPTO'};
            $shipTo = [];
            $shipTo['name'] = $shipToName;
            $shipTo['address1'] = (string) $intacctShipTo->{'MAILADDRESS'}->{'ADDRESS1'};
            $shipTo['address2'] = (string) $intacctShipTo->{'MAILADDRESS'}->{'ADDRESS2'};
            $shipTo['city'] = (string) $intacctShipTo->{'MAILADDRESS'}->{'CITY'};
            $shipTo['state'] = (string) $intacctShipTo->{'MAILADDRESS'}->{'STATE'};
            $shipTo['postal_code'] = (string) $intacctShipTo->{'MAILADDRESS'}->{'ZIP'};
            if ($country = (string) $intacctShipTo->{'MAILADDRESS'}->{'COUNTRYCODE'}) {
                $shipTo['country'] = $country;
            }
            $record['ship_to'] = $shipTo;
        }

        // custom mapping
        if ($this->customMapping) {
            foreach ((array) $this->customMapping as $source => $destination) {
                if ($value = $this->mapper->getNestedXmlValue($intacctInvoice, $source)) {
                    // Convert fields from the Intacct format to ours
                    $company = $this->tenant->get();
                    $value = $this->mapper->parseIntacctValue(Invoice::class, $destination, $value, $company);

                    array_set($record, $destination, $value);
                }
            }
        }

        if ($entity_id = (string) $intacctInvoice->{'MEGAENTITYID'}) {
            $record['metadata']['intacct_entity'] = $entity_id;
        }

        return $record;
    }

    /**
     * This determines the customer parameters
     * from an Intacct bill to contact.
     *
     * @throws TransformException when the customer cannot be determined
     */
    public function buildCustomerFromIntacctBillTo(SimpleXMLElement $intacctInvoice): AccountingCustomer
    {
        $billTo = $intacctInvoice->{'BILLTO'};
        $customer = [
            'name' => (string) $billTo->{'PRINTAS'},
            'address1' => (string) $billTo->{'MAILADDRESS'}->{'ADDRESS1'},
            'address2' => (string) $billTo->{'MAILADDRESS'}->{'ADDRESS2'},
            'city' => (string) $billTo->{'MAILADDRESS'}->{'CITY'},
            'state' => (string) $billTo->{'MAILADDRESS'}->{'STATE'},
            'postal_code' => (string) $billTo->{'MAILADDRESS'}->{'ZIP'},
            'phone' => (string) $billTo->{'PHONE1'},
            'metadata' => [
                'intacct_customer_number' => trim($intacctInvoice->{'CUSTVENDID'}),
            ],
        ];

        if ($country = (string) $billTo->{'MAILADDRESS'}->{'COUNTRYCODE'}) {
            $customer['country'] = $country;
        }

        // email address(es)
        $emails = [];
        if ($email = (string) $billTo->{'EMAIL1'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        }

        if ($email = (string) $billTo->{'EMAIL2'}) {
            $emails = array_merge($emails, ImportHelper::parseEmailAddress($email));
        }

        return new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: (string) $intacctInvoice->{'BILLTOKEY'},
            values: $customer,
            emails: $emails,
        );
    }

    /**
     * This determines the customer parameters from an Intacct customer.
     *
     * @throws TransformException
     */
    public function buildCustomerFromIntacctArCustomer(SimpleXMLElement $intacctInvoice): ?AccountingCustomer
    {
        return new AccountingCustomer(
            integration: IntegrationType::Intacct,
            accountingId: (string) $intacctInvoice->{'CUSTREC'},
            values: [
                'name' => (string) $intacctInvoice->{'CUSTVENDNAME'},
                'number' => (string) $intacctInvoice->{'CUSTVENDID'},
            ],
        );
    }

    private function buildLineItems(SimpleXMLElement $intacctInvoice, array $record): array
    {
        $record['items'] = [];
        $record['installments'] = [];
        $currency = $record['currency'] ?? $this->defaultCurrency;

        if ($intacctInvoice->{'SODOCUMENTENTRIES'}->count() > 0) {
            foreach ($intacctInvoice->{'SODOCUMENTENTRIES'}->{'sodocumententry'} as $intacctLine) {
                // clean up the line item name
                // remove any trailing ':'
                $name = trim((string) $intacctLine->{'ITEMDESC'});
                if (str_ends_with($name, ':')) {
                    $name = substr($name, 0, -1);
                }

                $item = [
                    'name' => $name,
                    'metadata' => [],
                ];

                // determine the true quantity
                $quantity = (float) $intacctLine->{'QUANTITY'};
                $multiplier = (float) $intacctLine->{'MULTIPLIER'};
                if ($multiplier > 1) {
                    $quantity *= $multiplier;
                }

                // determine the unit cost
                // If a contract line item is used and a discount % is in place
                // it is possible for the item price to not include the discount. However,
                // other types of invoices do exclude the discount % from the item price.
                // In order to detect this we look at the extended price and calculate the
                // unit cost.

                if ($this->hasMultiCurrency) {
                    $unitCost = (float) $intacctLine->{'TRX_PRICE'};
                    $extendedPrice = Money::fromDecimal($currency, (float) $intacctLine->{'TRX_VALUE'});
                } else {
                    $unitCost = (float) $intacctLine->{'UIPRICE'};
                    $extendedPrice = Money::fromDecimal($currency, (float) $intacctLine->{'UIVALUE'});
                }

                $itemAmount = Money::fromDecimal($currency, $unitCost * $quantity);
                if (!$itemAmount->equals($extendedPrice)) {
                    $item['metadata']['intacct_quantity'] = $quantity;
                    $quantity = 1;
                    $unitCost = $extendedPrice->toDecimal();
                }

                $item['quantity'] = $quantity;
                $item['unit_cost'] = $unitCost;

                // custom mapping
                if ($this->customItemMapping) {
                    foreach ((array) $this->customItemMapping as $source => $destination) {
                        if ($value = $this->mapper->getNestedXmlValue($intacctLine, $source)) {
                            // Convert fields from the Intacct format to ours
                            $company = $this->tenant->get();
                            $value = $this->mapper->parseIntacctValue(LineItem::class, $destination, $value, $company);

                            array_set($item, $destination, $value);
                        }
                    }
                }
                $record['items'][] = $item;

                // look for custom payment plan (cpp) import
                $cppDocType = $this->paymentPlanImportSettings->document_type ?? null;

                if ($this->documentType == $cppDocType) {
                    if ($this->customPaymentPlanMapping) {
                        $installment = [];

                        foreach ((array) $this->customPaymentPlanMapping as $source => $destination) {
                            if ($value = $this->mapper->getNestedXmlValue($intacctLine, $source)) {
                                // Convert fields from the Intacct format to ours
                                $company = $this->tenant->get();
                                $value = $this->mapper->parseIntacctValue(PaymentPlanInstallment::class, $destination, $value, $company);

                                array_set($installment, $destination, $value);
                            }
                        }

                        $record['installments'][] = $installment;
                    }
                }
            }
        }

        return $record;
    }

    private function buildSubtotalLines(SimpleXMLElement $intacctInvoice, array $record): ?array
    {
        $tax = 0;
        $discount = 0;

        if ($intacctInvoice->{'SUBTOTALS'}->count() > 0) {
            foreach ($intacctInvoice->{'SUBTOTALS'}->{'sodocumentsubtotals'} as $intacctSubtotal) {
                // Ignore lines which do not have a record number because this means
                // it is a display line, e.g. the subtotal and total amount. If we were
                // to include these then the invoice amount would be incorrect.
                if (0 == $intacctSubtotal->{'RECORDNO'}->count()) {
                    continue;
                }

                if ($this->hasMultiCurrency) {
                    $amount = (float) $intacctSubtotal->{'TRX_TOTAL'};
                } else {
                    $amount = (float) $intacctSubtotal->{'TOTAL'};
                }

                if (!$amount) {
                    continue;
                }

                // check if the subtotal meets our location ID criteria
                if ($this->locationIdFilter && $intacctSubtotal->{'LOCATIONID'} != $this->locationIdFilter) {
                    return null;
                }

                // clean up the line item name
                // remove any trailing ':'
                $name = trim((string) $intacctSubtotal->{'DESCRIPTION'});
                if (str_ends_with($name, ':')) {
                    $name = substr($name, 0, -1);
                }

                $subtotalName = strtolower($name);
                if ('sales tax' == $subtotalName) {
                    $tax += $amount;
                } elseif ('discount' == $subtotalName) {
                    $discount += $amount;
                } else {
                    $record['items'][] = [
                        'name' => $name,
                        'unit_cost' => $amount,
                    ];
                }
            }
        }

        $record['tax'] = $tax;
        $record['discount'] = -$discount;

        return $record;
    }
}
