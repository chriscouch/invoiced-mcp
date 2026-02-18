<?php

namespace App\Core\I18n;

class Countries
{
    private static array $data;

    /**
     * Gets the list of countries.
     */
    public function all(): array
    {
        if (!isset(self::$data)) {
            self::$data = require dirname(__DIR__, 3).'/assets/countries.php';
        }

        return self::$data;
    }

    /**
     * Gets a country by its ISO-3166-1 alpha-2 code.
     */
    public function get(string $alpha2Code): ?array
    {
        $alpha2Code = strtoupper($alpha2Code);
        $countries = $this->all();

        foreach ($countries as $country) {
            if ($country['code'] === $alpha2Code) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Gets a country by its ISO-3166-1 alpha-3 code.
     */
    public function getFromAlpha3(string $alpha3Code): ?array
    {
        $alpha3Code = strtoupper($alpha3Code);
        $countries = $this->all();

        foreach ($countries as $country) {
            if ($country['alpha3Code'] === $alpha3Code) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Gets a country by its ISO-3166-1 numeric code.
     */
    public function getFromNumeric(int $numericCode): ?array
    {
        $countries = $this->all();

        foreach ($countries as $country) {
            if ($country['numeric'] === $numericCode) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Gets a country by its English name.
     */
    public function getFromName(?string $name): ?array
    {
        $name = strtolower((string) $name);
        $countries = $this->all();

        foreach ($countries as $country) {
            if (strtolower($country['country']) === $name) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Checks if a country code exists for a given 2-letter code.
     */
    public function exists(string $code): bool
    {
        return is_array($this->get($code));
    }

    /**
     * Checks if a country is valid.
     *
     * @param string $code
     */
    public static function validateCountry(mixed $code): bool
    {
        if (!$code) {
            return true;
        }

        return (new self())->exists($code);
    }

    public function getStateShortName(?string $stateName, ?string $countryName): ?string
    {
        if (!$stateName || !$countryName) {
            return null;
        }

        if (strlen($stateName) <= 3) {
            return strtoupper($stateName);
        }

        $country = $this->get($countryName);
        if (!isset($country['states'])) {
            return $stateName;
        }

        foreach ($country['states'] as $state) {
            if (strtoupper($state['name']) === strtoupper($stateName)) {
                return $state['code'];
            }
        }

        return strtoupper($stateName);
    }
}
