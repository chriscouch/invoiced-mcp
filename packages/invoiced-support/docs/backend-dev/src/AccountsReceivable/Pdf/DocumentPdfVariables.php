<?php

namespace App\AccountsReceivable\Pdf;

use App\Core\I18n\AddressFormatter;
use App\Core\I18n\Countries;
use App\Core\Utils\Enums\ObjectType;
use App\AccountsReceivable\Libs\InvoiceCalculator;
use App\AccountsReceivable\Models\Discount;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Models\ShippingDetail;
use App\AccountsReceivable\Models\Tax;
use App\Metadata\Libs\CustomFieldRepository;
use App\Metadata\Libs\MetadataFormatter;
use App\SalesTax\Calculator\AvalaraTaxCalculator;
use App\Themes\Interfaces\PdfVariablesInterface;
use App\Themes\Models\Theme;
use NumberFormatter;

/**
 * View model for document PDF templates.
 */
class DocumentPdfVariables implements PdfVariablesInterface
{
    private static array $hideWithSingleQuantity = [
        'service',
        'expense',
        'shipping',
    ];

    private static array $appliedRateNames = [
        'discounts' => 'Discount',
        'taxes' => 'Tax',
        'shipping' => 'Shipping',
    ];

    public function __construct(protected ReceivableDocument $document)
    {
    }

    public function generate(Theme $theme, array $opts = []): array
    {
        $variables = $this->document->toArray();

        $company = $this->document->tenant();
        $company->useTimezone();
        $customer = $this->document->customer();
        $dateFormat = $this->document->dateFormat();
        $moneyFormat = $customer->moneyFormat();
        $htmlify = $opts['htmlify'] ?? true;

        // calculate the invoice so we have access to the computed rates
        $calculatedInvoice = InvoiceCalculator::calculate($variables['currency'], $variables['items'], $variables['discounts'], $variables['taxes'], $variables['shipping']);

        // format line items
        $isTaxed = count($calculatedInvoice->rates['taxes']) > 0;
        $isDiscounted = count($calculatedInvoice->rates['discounts']) > 0;

        $unitCostOptions = [];
        $precision = $company->accounts_receivable_settings->unit_cost_precision;
        if (null !== $precision) {
            $unitCostOptions['precision'] = $precision;
        }

        // line item custom fields (visible) -- for formatting metadata
        $lineItemCustomFields = CustomFieldRepository::get($company)->getFieldsForObject('line_item', true);

        $lineItemCustomFieldNames = [];
        foreach ($lineItemCustomFields as $field) {
            $lineItemCustomFieldNames[$field->id] = $field->name;
        }

        foreach ($variables['items'] as &$item) {
            // metadata
            $item['metadata'] = (array) $item['metadata'];

            // line item custom fields - check visibility
            $lineItemCustomFieldValues = [];
            foreach ($item['metadata'] as $id => $value) {
                if ($name = $lineItemCustomFieldNames[$id] ?? null) {
                    $lineItemCustomFieldValues[$name] = $value;
                }
            }
            $item['custom_fields'] = $lineItemCustomFieldValues;

            // htmlify the values when enabled
            if ($htmlify) {
                $item['name'] = htmlentities((string) $item['name'], ENT_QUOTES);
                $item['quantity'] = $this->number_format_no_round($item['quantity'], $moneyFormat['locale']);
                $item['unit_cost'] = $this->document->currencyFormatHtml($item['unit_cost'], $unitCostOptions);
                $item['amount'] = $this->document->currencyFormatHtml($item['amount']);
                $item['description'] = nl2br(htmlentities((string) $item['description'], ENT_QUOTES));

                foreach ($item['metadata'] as &$lineItemValue) {
                    $lineItemValue = nl2br(htmlentities((string) $lineItemValue, ENT_QUOTES));
                }
            }

            // add a billing period
            if (isset($item['period_start']) && $item['period_start']) {
                $period = date($dateFormat, $item['period_start']).' - '.date($dateFormat, $item['period_end']);
                if ($item['prorated']) {
                    $period = 'Proration for '.$period;
                }
                $item['billing_period'] = $period;
            }

            // do not show a quantity/unit cost when the quantity=1
            // and the line is a service, expense, or shipping type
            if ('1' === $item['quantity'] && in_array($item['type'], self::$hideWithSingleQuantity)) {
                $item['unit_cost'] = '';
                $item['quantity'] = '';
            }

            // build a list of the line item's applied rates
            $rateSummary = [];
            $appliedRates = [
                'discounts' => ObjectType::Coupon,
                'taxes' => ObjectType::TaxRate,
            ];
            foreach ($appliedRates as $type => $objectType) {
                $k = $objectType->typeName();

                foreach ($item[$type] as $appliedRate) {
                    // determine applied rate's name
                    if ($appliedRate[$k]) {
                        $summary = $appliedRate[$k]['name'].': ';
                        if ($appliedRate[$k]['is_percent']) {
                            $summary .= $appliedRate[$k]['value'].'%';
                        } elseif ($htmlify) {
                            $summary .= $this->document->currencyFormatHtml($appliedRate[$k]['value']);
                        } else {
                            $summary .= $this->document->currencyFormat($appliedRate[$k]['value']);
                        }
                    } else {
                        $name = $this->getAppliedRateName($type);
                        $summary = $name.': ';

                        if ($htmlify) {
                            $summary .= $this->document->currencyFormatHtml($appliedRate['amount']);
                        } else {
                            $summary .= $this->document->currencyFormat($appliedRate['amount']);
                        }
                    }

                    $rateSummary[] = $summary;
                }
            }

            if (!$item['discountable'] && $isDiscounted) {
                $rateSummary[] = 'Not Discounted';
            }

            if (!$item['taxable'] && $isTaxed) {
                $rateSummary[] = 'Not Taxed';
            }

            $item['rates'] = implode(', ', $rateSummary);
        }

        // format rates
        $rates = [];
        $totalDiscount = 0;
        $appliedRates = [
            'discounts' => ObjectType::Coupon,
            'taxes' => ObjectType::TaxRate,
            'shipping' => ObjectType::ShippingRate,
        ];
        foreach ($appliedRates as $type => $objectType) {
            $k = $objectType->typeName();

            foreach ($calculatedInvoice->rates[$type] as $appliedRate) {
                if ($htmlify) {
                    $appliedRate['total'] = $this->document->currencyFormatHtml($appliedRate['accumulated_total']);
                } else {
                    $appliedRate['total'] = $appliedRate['accumulated_total'];
                }

                // determine applied rate's name
                if ($appliedRate[$k] && AvalaraTaxCalculator::TAX_CODE != $appliedRate[$k]['id']) {
                    $appliedRate['name'] = $appliedRate[$k]['name'];
                    if ($appliedRate['in_subtotal'] && $appliedRate[$k]['is_percent']) {
                        $appliedRate['name'] .= ' ('.$appliedRate[$k]['value'].'%)';
                    }
                } else {
                    $appliedRate['name'] = $this->getAppliedRateName($type);
                }

                if ('discounts' === $type) {
                    if ($appliedRate['expires']) {
                        $appliedRate['expires'] = date($dateFormat, $appliedRate['expires']);
                    }

                    $totalDiscount += $appliedRate['accumulated_total'];
                }

                $rates[] = $appliedRate;
            }
        }
        $variables['rates'] = $rates;

        $variables['date'] = ($variables['date'] > 0) ? date($dateFormat, $variables['date']) : '';

        $variables['show_subtotal'] = count($rates) > 0;
        $variables['discountedSubtotal'] = $variables['subtotal'] - $totalDiscount;

        if ($htmlify) {
            $variables['url'] = htmlspecialchars($variables['url']);
            $variables['notes'] = nl2br(htmlentities((string) $variables['notes'], ENT_QUOTES));

            // currency format totals
            $variables['discountedSubtotal'] = $this->document->currencyFormatHtml($variables['discountedSubtotal']);
            $variables['subtotal'] = $this->document->currencyFormatHtml($variables['subtotal']);
            $variables['total'] = $this->document->currencyFormatHtml($variables['total']);
        }

        // ship to
        if ($shipTo = $this->document->ship_to) {
            $variables['ship_to'] = [
                'attention_to' => $shipTo->attention_to,
                'name' => $shipTo->name,
                'address' => $this->shipToAddress($shipTo),
            ];

            if ($htmlify) {
                $variables['ship_to']['address'] = nl2br(htmlentities((string) $variables['ship_to']['address'], ENT_QUOTES));
            }
        }

        // metadata
        $variables['metadata'] = (array) $variables['metadata'];

        // custom fields
        $customFields = CustomFieldRepository::get($company)->getFieldsForObject($this->document->object, true);
        $metadataFormatter = new MetadataFormatter($company, $customer, true);

        $variables['custom_fields'] = [];
        foreach ($customFields as $field) {
            // first look for the field in the document metadata
            $k = $field->id;
            $value = null;
            if (property_exists($this->document->metadata, $k)) {
                $value = $this->document->metadata->$k;
            } elseif (property_exists($customer->metadata, $k)) {
                // if not set then inherit the value from the customer's metadata
                $value = $customer->metadata->$k;
            }

            if (null !== $value) {
                $variables['custom_fields'][] = [
                    'name' => $field->name,
                    'value' => $metadataFormatter->format($field, $value),
                ];
            }
        }

        return $variables;
    }

    //
    // Helpers
    //

    /**
     * Formats a number, i.e. 3000 -> 3,000, to a given locale without
     * performing any rounding since PHP's number_format() implicitly rounds.
     */
    private function number_format_no_round(float $n, string $locale): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 5);

        return (string) $formatter->format($n);
    }

    /**
     * Gets the name of a one-off applied rate, i.e. "Tax" or "Discount".
     */
    private function getAppliedRateName(string $type): string
    {
        if ('taxes' == $type) {
            $countries = new Countries();
            if ($country = $countries->get((string) $this->document->tenant()->country)) {
                if (isset($country['tax_label'])) {
                    return $country['tax_label'];
                }
            }
        }

        return self::$appliedRateNames[$type];
    }

    /**
     * Generate a shipping address.
     */
    private function shipToAddress(ShippingDetail $shipTo): string
    {
        $formatter = new AddressFormatter();
        $formatter->setTo($shipTo);

        // only show the country line when the customer and
        // company are in different countries
        $address = $formatter->buildAddress(false);
        $showCountry = $address->getCountryCode() != $this->document->tenant()->country;
        $options = [
            'showCountry' => $showCountry,
            'showName' => false,
        ];

        return $formatter->formatAddress($address, $options);
    }
}
