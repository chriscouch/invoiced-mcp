<?php

namespace App\Metadata\ValueObjects;

use App\Metadata\Models\CustomField;
use Exception;

/**
 * Represents a custom field restriction.
 */
class CustomFieldRestriction implements \JsonSerializable
{
    /**
     * @param string[] $values
     */
    public function __construct(private string $key, private array $values)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return string[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'values' => $this->values];
    }

    /**
     * @throws Exception
     */
    public static function validateRestrictions(mixed $restrictions): void
    {
        if (!is_array($restrictions)) {
            throw new Exception('Restrictions input is invalid');
        }

        $keys = array_keys($restrictions);
        if (count($keys) > 3) {
            throw new Exception('There can only be restrictions on up to 3 custom fields');
        }

        foreach ($restrictions as $key => $restriction) {
            if (!CustomField::validateID($key)) {
                throw new Exception('Invalid custom field ID: '.$key);
            }

            if (!is_array($restriction)) {
                throw new Exception('Restriction value for '.$key.' is invalid');
            }

            if (0 == count($restriction) || count($restriction) > 10) {
                throw new Exception('Restriction value for '.$key.' must contain at least one and no more than 10 values');
            }

            foreach ($restriction as $value) {
                if (!is_string($value) || strlen($value) > 255) {
                    throw new Exception('Restriction value for custom field `'.$key.'` must be a string no greater than 255 characters: '.$value);
                }
            }
        }
    }
}
