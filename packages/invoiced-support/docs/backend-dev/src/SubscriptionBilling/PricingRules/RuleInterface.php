<?php

namespace App\SubscriptionBilling\PricingRules;

/**
 * The contract for a type of pricing rule.
 */
interface RuleInterface
{
    /**
     * Applies the transformation to a matching line item.
     *
     * @param mixed $value transformation value
     *
     * @return array transformed line items
     */
    public function transform(array $lineItem, mixed $value): array;

    /**
     * Validates a given value for this rule.
     */
    public function validate(mixed $value): bool;

    /**
     * Gets the last validation error.
     */
    public function getLastValidationError(): string;

    /**
     * Serializes a transformation value into a string
     * for storage. The value has already been validated
     * at this point. This method should be robust enough
     * that if an already serialized value is passed it would
     * not be serialized twice.
     */
    public function serialize(mixed $value): string;

    /**
     * De-serializes a value that was previously
     * serialized by this rule.This method should be robust enough
     * that if an already de-serialized value is passed it would
     * not be de-serialized twice.
     */
    public function deserialize(string $value): mixed;
}
