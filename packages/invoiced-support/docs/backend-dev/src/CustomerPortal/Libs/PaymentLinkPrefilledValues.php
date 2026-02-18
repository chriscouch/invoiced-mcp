<?php

namespace App\CustomerPortal\Libs;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\PaymentLinkField;
use App\CustomerPortal\ValueObjects\PrefilledValues;

final class PaymentLinkPrefilledValues
{
    private const ALLOWED = [
        'first_name',
        'last_name',
        'company',
        // payment link
        'amount',
        'invoice_number',
        // customer profile
        'customer.number',
        'customer.email',
        'customer.phone',
        'customer.tax_id',
        // billing address
        'customer.address1',
        'customer.address2',
        'customer.city',
        'customer.state',
        'customer.postal_code',
        'customer.country',
        // shipping address
        'shipping.name',
        'shipping.address1',
        'shipping.address2',
        'shipping.city',
        'shipping.state',
        'shipping.postal_code',
        'shipping.country',
        // payment source
        'payment_source.method',
    ];

    /**
     * This maps keys to a different parameter.
     */
    private const ALIASES = [
        'clientId' => 'customer.number',
        'client_identifier' => 'customer.number',
        'email' => 'customer.email',
        'phone' => 'customer.phone',
        'booking_reference' => 'invoice_number',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'address' => 'customer.address1',
        'address1' => 'customer.address1',
        'address2' => 'customer.address2',
        'city' => 'customer.city',
        'state' => 'customer.state',
        'zip' => 'customer.postal_code',
        'postal_code' => 'customer.postal_code',
        'country' => 'customer.country',
    ];

    /**
     * Builds a set of prefilled values from request input (eg query parameters)
     * for use on a payment link payment page.
     *
     * @param PaymentLinkField[] $fields
     */
    public static function make(array $input, ?Customer $customer, array $fields): PrefilledValues
    {
        $values = [];

        // generate the list of allowed field values
        $allowed = self::ALLOWED;
        foreach ($fields as $field) {
            $allowed[] = $field->getFormId();
        }

        // start with customer values, when given
        if ($customer) {
            foreach ($allowed as $keyName) {
                if (str_starts_with($keyName, 'customer.')) {
                    [, $property] = explode('.', $keyName);
                    array_set($values, $keyName, $customer->$property);
                }
            }
        }

        // flatten a potentially multi-dimensional input into dot notation
        // i.e. a['customer']['name'] -> a['customer.name']
        $input = array_dot($input);

        // rename aliased keys
        foreach (self::ALIASES as $from => $to) {
            if (isset($input[$from])) {
                $input[$to] = $input[$from];
            }
        }

        // build the filtered output
        foreach ($input as $k => $v) {
            if (in_array($k, $allowed)) {
                array_set($values, $k, $v);
            }
        }

        return new PrefilledValues($values);
    }
}
