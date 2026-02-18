<?php

namespace App\Metadata\Libs;

use App\Companies\Models\Company;
use App\Metadata\Models\CustomField;

/**
 * Repository class to retrieve custom fields from the data layer.
 */
class CustomFieldRepository
{
    const CUSTOM_FIELDS_LIMIT = 100;

    /** @var self[] */
    private static array $instances = [];

    /** @var CustomField[] */
    private array $customFields;

    public static function get(Company $company): self
    {
        if (!isset(self::$instances[$company->id()])) {
            self::$instances[$company->id()] = new self($company);
        }

        return self::$instances[$company->id()];
    }

    public function __construct(private Company $company)
    {
    }

    /**
     * Gets the custom fields for an object type.
     *
     * @param string $object              object type, i.e. `invoice`
     * @param bool   $customerVisibleOnly when true only returns fields visible to customers
     *
     * @return CustomField[]
     */
    public function getFieldsForObject(string $object, bool $customerVisibleOnly = false): array
    {
        if (!isset($this->customFields)) {
            $this->loadCustomFields();
        }

        $result = [];
        foreach ($this->customFields as $customField) {
            // sale object is an alias for union of credit note and invoice
            if ($customField->object != $object && ('sale' != $object || !in_array($customField->object, ['credit_note', 'invoice']))) {
                continue;
            }

            if ($customerVisibleOnly && !$customField->external) {
                continue;
            }

            $result[$customField->id] = $customField;
        }

        return $result;
    }

    /**
     * Gets the matching custom field for an object type.
     */
    public function getCustomField(string $object, string $id): ?CustomField
    {
        $customFields = $this->getFieldsForObject($object);
        if (!isset($customFields[$id])) {
            return null;
        }

        return $customFields[$id];
    }

    public function clearCache(): void
    {
        unset($this->customFields);
    }

    /**
     * Loads all custom fields for a company.
     */
    private function loadCustomFields(): void
    {
        $this->customFields = CustomField::queryWithTenant($this->company)
            ->first(self::CUSTOM_FIELDS_LIMIT);
    }
}
