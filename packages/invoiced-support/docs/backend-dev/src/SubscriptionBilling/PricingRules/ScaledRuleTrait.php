<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * Implements the validation for markdown/markup pricing rules.
 */
trait ScaledRuleTrait
{
    public function validate(mixed $value): bool
    {
        // determine if this is a % or fixed amount
        $isFixed = !str_contains($value, '%');

        if ($isFixed) {
            // validate a numeric value
            if (!is_numeric($value)) {
                $this->lastError = 'Could not validate rule value. Must be a number or percentage (i.e. "2%").';

                return false;
            }
        } else {
            // validate a percentage value
            if (!preg_match('/^(\d{1,3}(?:\.\d{1,2})?)%$/', $value)) {
                $this->lastError = 'Could not validate rule value. Must be a number or percentage (i.e. "2%").';

                return false;
            }
        }

        return true;
    }

    public function serialize(mixed $value): string
    {
        return (string) $value;
    }

    public function deserialize(mixed $value): mixed
    {
        return $value;
    }
}
