<?php

namespace App\AccountsReceivable\Libs;

use App\AccountsReceivable\Models\PaymentTerms;
use App\Core\Orm\Exception\DriverException;

class PaymentTermsFactory
{
    /**
     * Gets a payment terms model given a name.
     */
    public static function get(string $name): PaymentTerms
    {
        // Look up the terms
        if ($terms = self::lookup($name)) {
            return $terms;
        }

        // If the terms do not exist then we are going to create one
        $terms = new PaymentTerms(['name' => $name]);

        // Parse commonly used payment terms formats
        $name = trim(strtolower($terms));
        if (preg_match('/([\d]+)% ([\d]+) net ([\d]+)/i', $name, $matches)) {
            // parse X% Y NET D
            $terms->discount_is_percent = true;
            $terms->discount_value = (float) $matches[1];
            $terms->discount_expires_in_days = (int) $matches[2];
            $terms->due_in_days = (int) $matches[3];
        } elseif (preg_match('/net[\s-]([\d]+)/i', $name, $matches)) {
            // parse NET D
            $terms->due_in_days = (int) $matches[1];
        }

        // Save the terms
        self::save($terms);

        return $terms;
    }

    private static function lookup(string $name): ?PaymentTerms
    {
        return PaymentTerms::where('name', $name)->oneOrNull();
    }

    private static function save(PaymentTerms $terms): void
    {
        try {
            $terms->save();
        } catch (DriverException) {
            // If the model cannot be saved then ignore the failure.
            // This might happen if concurrently trying to write
            // terms with the same name.
        }
    }
}
