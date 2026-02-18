<?php

namespace App\Metadata\Libs;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\I18n\MoneyFormatter;
use App\Metadata\Models\CustomField;

/**
 * This class formats metadata values for display
 * in customer-facing scenarios, like emails, PDFs,
 * and the customer portal.
 */
class MetadataFormatter
{
    /**
     * @param bool $html when true, formats the field for display in HTML
     */
    public function __construct(
        private Company $company,
        private Customer $customer,
        private bool $html = false,
    ) {
    }

    public function format(CustomField $customField, mixed $value): mixed
    {
        $method = 'format_'.$customField->type;

        // Security: prevent XSS
        if ($this->html) {
            $value = htmlspecialchars($value);
        }

        return $this->$method($value, $customField);
    }

    /**
     * Formats a metadata value for display of type `string`.
     */
    public function format_string(mixed $value, CustomField $customField): string
    {
        return $value;
    }

    /**
     * Formats a metadata value for display of type `boolean`.
     */
    public function format_boolean(mixed $value, CustomField $customField): string
    {
        if ($value) {
            return 'Yes';
        }

        return 'No';
    }

    /**
     * Formats a metadata value for display of type `double`.
     */
    public function format_double(mixed $value, CustomField $customField): string
    {
        return $value;
    }

    /**
     * Formats a metadata value for display of type `enum`.
     */
    public function format_enum(mixed $value, CustomField $customField): string
    {
        return $value;
    }

    /**
     * Formats a metadata value for display of type `date`.
     */
    public function format_date(mixed $value, CustomField $customField): string
    {
        if (is_numeric($value)) {
            $dateFormat = $this->company->date_format;

            return date($dateFormat, (int) $value);
        }

        return $value;
    }

    /**
     * Formats a metadata value for display of type `money`.
     */
    public function format_money(mixed $value, CustomField $customField): string
    {
        $parts = explode(',', $value);
        if (2 === count($parts)) {
            $currency = $parts[0];
            $amount = $parts[1];
        } else {
            $currency = $this->company->currency;
            $amount = $value;
        }

        $options = $this->customer->moneyFormat();
        if ($this->html) {
            return MoneyFormatter::get()->currencyFormatHtml($amount, $currency, $options);
        }

        return MoneyFormatter::get()->currencyFormat($amount, $currency, $options);
    }
}
