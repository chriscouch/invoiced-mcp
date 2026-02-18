<?php

namespace App\Core\I18n;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ShippingDetail;
use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use CommerceGuys\Addressing\Country\CountryRepository;
use CommerceGuys\Addressing\Formatter\DefaultFormatter;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository;
use App\Core\Orm\Model;

/**
 * Handles address formatting for display to end users.
 */
class AddressFormatter
{
    private Model $model;
    private DefaultFormatter $formatter;

    /**
     * Sets who the address belongs to in the "from" perspective.
     *
     * @return $this
     */
    public function setFrom(Model $from)
    {
        $this->model = $from;

        return $this;
    }

    /**
     * Sets who the address belongs to in the "to" perspective.
     *
     * @return $this
     */
    public function setTo(Model $to)
    {
        $this->model = $to;

        return $this;
    }

    public function getFormatter(): DefaultFormatter
    {
        if (isset($this->formatter)) {
            return $this->formatter;
        }

        $addressFormatRepository = new AddressFormatRepository();
        $countryRepository = new CountryRepository();
        $subdivisionRepository = new SubdivisionRepository();
        $this->formatter = new DefaultFormatter($addressFormatRepository, $countryRepository, $subdivisionRepository);

        return $this->formatter;
    }

    /**
     * Formats this model's address.
     */
    public function format(array $options = []): string
    {
        $options = array_replace([
            'showName' => true,
        ], $options);

        $address = $this->buildAddress($options['showName']);

        return $this->formatAddress($address, $options);
    }

    /**
     * Builds an address object from the model.
     */
    public function buildAddress(bool $showName = true): Address
    {
        $address = new Address();

        if ($showName && $name = trim((string) $this->model->name)) {
            $address = $address->withGivenName($name);
        }

        if ($line1 = trim((string) $this->model->address1)) {
            $address = $address->withAddressLine1($line1);
        }

        if ($line2 = trim((string) $this->model->address2)) {
            $address = $address->withAddressLine2($line2);
        }

        if ($city = trim((string) $this->model->city)) {
            $address = $address->withLocality($city);
        }

        if ($state = trim((string) $this->model->state)) {
            $address = $address->withAdministrativeArea($state);
        }

        if ($postalCode = trim((string) $this->model->postal_code)) {
            $address = $address->withPostalCode($postalCode);
        }

        $countryCode = strtoupper(trim((string) $this->model->country));

        // if the country is missing for the customer then use the company country
        if (!$countryCode && $this->model instanceof Customer) {
            $countryCode = strtoupper(trim((string) $this->model->tenant()->country));
        }

        // if country is missing for the ship to address then inherit the customer or country
        if (!$countryCode && $this->model instanceof ShippingDetail) {
            if ($parent = $this->model->parent()) {
                $customer = $parent->customer();
                if ($customer && $customerCountry = $customer->country) {
                    $countryCode = $customerCountry;
                }
            }

            // if country is still not available then inherit company country
            if (!$countryCode && $companyCountry = trim((string) $this->model->tenant()->country)) {
                $countryCode = $companyCountry;
            }
        }

        $address = $address->withCountryCode($countryCode);

        return $address;
    }

    /**
     * Formats an address entity according to preferences.
     */
    public function formatAddress(Address $address, array $options = []): string
    {
        $options = array_replace([
            'showCountry' => false,
            'showExtra' => true,
            'html' => false,
        ], $options);

        // These are options supported by the addressing library.
        // The other options are used by this function.
        $addressOptions = [
            'html' => $options['html'],
        ];

        try {
            $formatter = $this->getFormatter();
            $lines = explode("\n", $formatter->format($address, $addressOptions));
        } catch (\InvalidArgumentException) {
            // we are going to ignore any validation errors and return an empty address
            return '';
        }

        // show country option
        // this will search each line for the country name
        // and only include lines that do not contain the country
        $countryCode = $address->getCountryCode();
        $countries = new Countries();
        $country = $countries->get($countryCode);
        if (!$options['showCountry'] && $country) {
            $filteredLines = [];
            foreach ($lines as $line) {
                if ($line !== $country['country']) {
                    $filteredLines[] = $line;
                }
            }
            $lines = $filteredLines;
        }

        // Tax ID
        if (!isset($options['showTaxId'])) {
            $countryShowsTaxId = !isset($country['hide_tax_id']);
            $options['showTaxId'] = $countryShowsTaxId;
        }

        if ($options['showTaxId'] && $this->model->tax_id) {
            $taxIdName = 'Tax ID';

            if (isset($country['tax_id']) && $this->model->type) {
                $taxIdName = $country['tax_id'][$this->model->type];
            }

            $lines[] = $taxIdName.': '.$this->model->tax_id;
        }

        // Extra Info Line
        if ($options['showExtra'] && $this->model->address_extra) {
            $lines[] = $this->model->address_extra;
        }

        return implode("\n", $lines);
    }
}
