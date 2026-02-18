<?php

namespace App\Metadata\Libs;

use App\Companies\Models\Company;
use App\Metadata\Models\CustomField;

/**
 * Provides type casting for metadata values based
 * on the custom field settings.
 */
class LegacyMetadataMarshaler
{
    public function __construct(private Company $company)
    {
    }

    /**
     * Casts a metadata value coming from storage.
     */
    public function castToStorage(string $object, string $id, mixed $value): string
    {
        $customField = CustomFieldRepository::get($this->company)->getCustomField($object, $id);
        if (!$customField) {
            return $this->to_storage_no_type($value);
        }

        $method = 'to_storage_'.$customField->type;

        return $this->$method($value, $customField);
    }

    /**
     * Casts a metadata value going into storage.
     */
    public function castFromStorage(string $object, string $id, mixed $value): mixed
    {
        $customField = CustomFieldRepository::get($this->company)->getCustomField($object, $id);
        if (!$customField) {
            return $value;
        }

        $method = 'from_storage_'.$customField->type;

        return $this->$method($value, $customField);
    }

    //
    // Casting to Storage
    //

    /**
     * Casts a value that does not have a custom field type.
     */
    public function to_storage_no_type(mixed $value): string
    {
        // encode array/object values to JSON
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value);
        }

        // encode boolean values as 0 or 1
        if (is_bool($value)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);

            return $value ? '1' : '0';
        }

        return $value;
    }

    /**
     * Casts a custom field of type `string`.
     */
    public function to_storage_string(mixed $value, CustomField $customField): string
    {
        if (!$value) {
            return '';
        }

        // encode array/object values to JSON
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Casts a custom field of type `boolean`.
     */
    public function to_storage_boolean(mixed $value, CustomField $customField): string
    {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $value ? '1' : '0';
    }

    /**
     * Casts a custom field of type `double`.
     */
    public function to_storage_double(mixed $value, CustomField $customField): float
    {
        return (float) filter_var($value, FILTER_VALIDATE_FLOAT);
    }

    /**
     * Casts a custom field of type `enum`.
     */
    public function to_storage_enum(mixed $value, CustomField $customField): string
    {
        if (!$value) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Casts a custom field of type `date`.
     */
    public function to_storage_date(mixed $value, CustomField $customField): ?int
    {
        if (!$value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Casts a custom field of type `money`.
     */
    public function to_storage_money(mixed $value, CustomField $customField): string
    {
        return (string) $value;
    }

    //
    // Casting from Storage
    //

    /**
     * Casts a custom field of type `string`.
     */
    public function from_storage_string(mixed $value, CustomField $customField): string
    {
        if (!$value) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Casts a custom field of type `boolean`.
     */
    public function from_storage_boolean(mixed $value, CustomField $customField): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Casts a custom field of type `double`.
     */
    public function from_storage_double(mixed $value, CustomField $customField): float
    {
        return (float) filter_var($value, FILTER_VALIDATE_FLOAT);
    }

    /**
     * Casts a custom field of type `enum`.
     */
    public function from_storage_enum(mixed $value, CustomField $customField): string
    {
        if (!$value) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Casts a custom field of type `date`.
     */
    public function from_storage_date(mixed $value, CustomField $customField): ?int
    {
        if (!$value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Casts a custom field of type `money`.
     */
    public function from_storage_money(mixed $value, CustomField $customField): string
    {
        return (string) $value;
    }
}
