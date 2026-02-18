<?php

namespace App\Core\I18n;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneFormatter
{
    /**
     * Attempts to format a phone number according the given format.
     * If the phone number cannot be parsed for any reason then the
     * input value is returned.
     */
    public static function format(string $input, ?string $country, int $format = PhoneNumberFormat::NATIONAL): string
    {
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            $phoneNumberObject = $phoneNumberUtil->parse($input, $country);
        } catch (NumberParseException) {
            return $input;
        }

        return $phoneNumberUtil->format($phoneNumberObject, $format);
    }
}
