<?php

namespace App\Imports\Libs;

use App\Companies\Models\Company;
use App\Core\I18n\Countries;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Models\Import;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A collection of utilities used by import tools
 * and other similar components.
 */
final class ImportHelper
{
    const VALID_TRUE = ['1', 'true', 'on', 'yes'];
    const VALID_FALSE = ['0', 'false', 'off', 'no'];

    /**
     * Unambiguous date formats we can import.
     */
    private const DATE_FORMATS = [
        'M-d-Y', // Feb-28-2018
        'd-M-Y', // 28-Feb-2018
        'F-d-Y', // February-28-2018
        'Ymd',   // 20180228
        'Y-m-d', // 2018-02-28
    ];

    private const COUNTRY_DATE_FORMATS = [
        'US' => [
            'm/d/y', // 02/28/18
            'n/d/y', // 2/28/18
            'n/j/y', // 2/28/18
            'm/d/Y', // 02/28/2018
            'n/d/Y', // 2/28/2018
            'n/j/Y', // 2/28/2018
        ],
    ];

    /**
     * Parses a string into a float. Strips characters like commas and
     * dollar signs.
     *
     * @throws ValidationException if the number is invalid
     */
    public static function parseFloat(string $value): float
    {
        $stripped = (string) preg_replace("/[^-\d.]/", '', $value);

        if (0 === strlen($stripped)) {
            throw new ValidationException('Invalid number: '.$value);
        }

        if (!is_numeric($stripped)) {
            // This handles a special case where Excel formats
            // $0 amounts as "$-   ".
            if ('-' === $stripped) {
                return 0;
            }

            throw new ValidationException('Invalid number: '.$value);
        }

        // This handles a special case where Excel formats
        // negative amounts as "$(27.13)", wrapped in parentheses.
        // The number stripping will take out the parentheses so
        // we need to invert the stripped value
        if (str_contains($value, '(') && str_contains($value, ')')) {
            $stripped = -$stripped;
        }

        return (float) $stripped;
    }

    /**
     * Parses a string into an integer. Strips characters like commas and
     * dollar signs.
     *
     * @throws ValidationException if the number is invalid
     */
    public static function parseInt(string $value): int
    {
        $stripped = (string) preg_replace("/[^-\d.]/", '', (string) $value);

        if (0 === strlen($stripped)) {
            throw new ValidationException('Invalid number: '.$value);
        }

        if (!is_numeric($stripped)) {
            // This handles a special case where Excel formats
            // $0 amounts as "$-   ".
            if ('-' === $stripped) {
                return 0;
            }

            throw new ValidationException('Invalid number: '.$value);
        }

        // This handles a special case where Excel formats
        // negative amounts as "$(27.13)", wrapped in parentheses.
        // The number stripping will take out the parentheses so
        // we need to invert the stripped value
        if (str_contains($value, '(') && str_contains($value, ')')) {
            $stripped = -$stripped;
        }

        return (int) $stripped;
    }

    /**
     * Parses a date from a string to a unix timestamp.
     *
     * @throws ValidationException if the date cannot be parsed
     */
    public static function parseDateUnixTimestamp(string $date, ?string $country, bool $endOfDay): ?int
    {
        if ($date = self::parseDate($date, $country)) {
            $hour = $endOfDay ? 18 : 6;

            return $date->setTime($hour, 0)->getTimestamp();
        }

        return null;
    }

    /**
     * Parses a date from a string to a unix timestamp.
     *
     * @throws ValidationException if the date cannot be parsed
     */
    public static function parseDate(string $input, ?string $country = null): ?CarbonImmutable
    {
        if (!$input) {
            return null;
        }

        // Try to parse each of the ambiguous date formats until a match is found
        $date = false;
        $dateFormats = self::DATE_FORMATS;
        if (isset(self::COUNTRY_DATE_FORMATS[$country])) {
            $dateFormats = array_merge($dateFormats, self::COUNTRY_DATE_FORMATS[$country]);
        }

        foreach ($dateFormats as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $input);
                if (is_object($date)) {
                    break;
                }
            } catch (Throwable) {
                // intentionally ignore exceptions
            }
        }

        if (!$date) {
            throw new ValidationException('Could not validate date ('.$input.'). Must be in the format '.date('M-d-Y'));
        }

        if ($date->getTimestamp() <= 0) {
            throw new ValidationException('Could not validate date ('.$input.'). Must be in the format '.date('M-d-Y'));
        }

        return $date;
    }

    /**
     * Parses a state string that could be in a non-standard format.
     *
     * @param string $countryCode 2-digit country code
     */
    public static function parseState(string $input, string $countryCode): ?string
    {
        if (!$input) {
            return null;
        }

        $countries = new Countries();
        if ($country = $countries->get($countryCode)) {
            if (!isset($country['states'])) {
                return $input;
            }

            $inputLower = strtolower($input);
            $inputUpper = strtoupper($input);
            foreach ($country['states'] as $state) {
                if ($state['code'] == $inputUpper || strtolower($state['name']) == $inputLower) {
                    return $state['code'];
                }
            }
        }

        return $input;
    }

    /**
     * Parses a country string that could be in a non-standard format.
     */
    public static function parseCountry(string $input): ?string
    {
        $countries = new Countries();
        if (2 == strlen($input)) {
            // Verify the 2-digit country code is valid per our database.
            if ($country = $countries->get($input)) {
                return $country['code'];
            }

            return null;
        } elseif (3 == strlen($input)) {
            // If a 3-digit country is provided then attempt
            // to convert to a 2-digit code.
            if ($country = $countries->getFromAlpha3($input)) {
                return $country['code'];
            }

            return null;
        }

        // In all other cases attempt to convert the name to a 2-digit code.
        if ($country = $countries->getFromName($input)) {
            return $country['code'];
        }

        return null;
    }

    /**
     * Checks if an array element has a value. This means that there
     * is an element present and the string length is greater than 0.
     */
    public static function cellHasValue(array $values, string $key): bool
    {
        return isset($values[$key]) && strlen($values[$key]) > 0;
    }

    /**
     * Maps a numerically-index line according to a given property mapping.
     *
     * @param array $allowed allowed import properties
     */
    public static function mapRecord(array $mapping, array $line, array $allowed = []): array
    {
        $record = ['metadata' => new \stdClass()];
        foreach ($mapping as $index => $property) {
            $value = array_value($line, $index);

            // handle metadata columns
            if (str_starts_with($property, 'metadata.')) {
                if ('' === $value) {
                    $value = null;
                }
                $id = str_replace('metadata.', '', $property);
                $record['metadata']->$id = $value;
            } elseif (in_array($property, $allowed)) {
                // otherwise match for allowed properties
                array_set($record, $property, $value);
            }
        }

        if (0 === count((array) $record['metadata'])) {
            unset($record['metadata']);
        }

        return $record;
    }

    /**
     * Performs a raw mapping of line to it's column headers.
     * WARNING: this should not be used to generate records for import
     * but to help present a failing line to users only.
     */
    public static function mapRecordToColumns(array $mapping, array $line): array
    {
        $result = [];
        foreach ($mapping as $index => $property) {
            $result[$property] = array_value($line, $index);
        }

        return $result;
    }

    /**
     * This function takes an unsanitized email address
     * (or delimited email addresses) and parses it out into a single,
     * clean address for Invoiced. It takes a value that
     * might have extraneous white-space or multiple emails
     * separated by spaces, commas, or semicolons into
     * a single address.
     */
    public static function parseEmailAddress(string $input): array
    {
        $input = trim($input);
        $input = str_replace([',', ' '], ';', $input);
        $parts = explode(';', $input);

        $emails = [];
        foreach ($parts as $email) {
            $email = trim($email);
            if ($email) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Extracts the customer profile out of a record line.
     */
    public static function mapCustomerProfile(Company $company, array $record, array $properties): array
    {
        $customer = [];
        foreach ($record as $key => $value) {
            if (self::cellHasValue($properties, $key)) {
                $property = $properties[$key];
                if ('country' == $property) {
                    $customer[$property] = self::parseCountry($value);
                } else {
                    $customer[$property] = $value;
                }
            }
        }

        if (isset($customer['state'])) {
            $country = $record['country'] ?? '';
            $country = $country ?: $company->country;
            $customer['state'] = self::parseState($customer['state'], $country);
        }

        return $customer;
    }

    /**
     * Returns the record without the customer properties
     * from mapCustomerProfile().
     */
    public static function withoutCustomerProperties(array $record, array $properties): array
    {
        foreach ($properties as $k => $v) {
            unset($record[$k]);
        }

        return $record;
    }

    /**
     * Gets the timestamp of the last successful import
     * of the given type.
     */
    public static function getLastSuccessfulImportTimestamp(Import $import): ?int
    {
        $lastSuccessfulImport = Import::where('type', $import->type)
            ->where('status', Import::SUCCEEDED)
            ->sort('id DESC')
            ->oneOrNull();

        if (!$lastSuccessfulImport) {
            return null;
        }

        return $lastSuccessfulImport->created_at;
    }

    public static function parseBoolean(string $value): bool
    {
        $value = strtolower($value);

        if (in_array($value, self::VALID_TRUE)) {
            return true;
        }
        if (in_array($value, self::VALID_FALSE)) {
            return false;
        }

        throw new ValidationException('Invalid boolean value: '.$value.', allowed values are - '.implode(', ', array_merge(self::VALID_TRUE, self::VALID_FALSE)));
    }
}
