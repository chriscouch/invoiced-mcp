<?php

namespace App\CustomerPortal\ValueObjects;

/**
 * Instances of this class hold a bag of pre-filled values
 * for a submission form.
 */
class PrefilledValues
{
    public function __construct(private array $values)
    {
    }

    /**
     * Gets a given value for a key.
     */
    public function get(string $key): mixed
    {
        return array_value($this->values, $key);
    }

    /**
     * Gets all the pre-filled values.
     */
    public function all(): array
    {
        return $this->values;
    }
}
