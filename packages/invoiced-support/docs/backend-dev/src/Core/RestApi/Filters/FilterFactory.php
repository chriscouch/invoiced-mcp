<?php

namespace App\Core\RestApi\Filters;

use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\Orm\Model;
use App\Core\Orm\Property;
use App\Core\Orm\Relation\Relationship;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\ObjectConfiguration;
use BackedEnum;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FilterFactory
{
    /**
     * Gets a list of properties that are filterable for a given model class.
     *
     * @param class-string<Model> $modelClass
     *
     * @return string[]
     */
    public function getFilterableProperties(string $modelClass): array
    {
        try {
            $objectType = ObjectType::fromModelClass($modelClass)->typeName();
        } catch (RuntimeException) {
            // An exception here means the model does not have a registered definition
            // and we can assume there are no filterable properties.
            return [];
        }

        $objectConfiguration = ObjectConfiguration::get();
        if (!$objectConfiguration->exists($objectType)) {
            return [];
        }

        $fields = $objectConfiguration->getFields($objectType);
        $result = [];
        foreach ($fields as $key => $field) {
            if ($field['filterable']) {
                $result[] = $key;
                // if this is a join then allow filtering by the join column also
                if ('join' == $field['type']) {
                    $parentColumn = $field['parent_column'] ?? $key.'_id';
                    $result[] = $parentColumn;
                }
            }
        }

        $result = array_unique($result);
        sort($result);

        return $result;
    }

    /**
     * Parses a list filter with the simple syntax.
     *
     * @param class-string<Model> $modelClass
     *
     * @throws InvalidRequest when the input is not valid
     */
    public function parseSimpleFilter(array $input, string $modelClass, ?array $allowed = null): ListFilter
    {
        $allowed ??= $this->getFilterableProperties($modelClass);

        $filters = [];
        foreach ($input as $originalField => $value) {
            // check if in the list of allowed filter properties
            if (!$this->isFieldAllowed($originalField, $allowed)) {
                throw new InvalidRequest("Invalid filter parameter: $originalField");
            }

            // convert a belongs to relationship filter to the local key
            $property = $modelClass::definition()->get($originalField);
            $field = $originalField;
            if ($property) {
                $field = $this->getRenamedField($property, $originalField);
            }

            // the simple filter is always an equal comparison
            $operator = FilterOperator::Equal;

            // marshal value
            if (is_array($value) || is_object($value)) {
                throw new InvalidRequest('Invalid value for `'.$originalField.'` filter parameter');
            }

            if ($property && $value) {
                $value = $this->marshalValue($property, $originalField, $value, $operator);
            }

            $filters[] = new FilterCondition(
                operator: $operator,
                field: $field,
                value: $value,
            );
        }

        return new ListFilter($filters);
    }

    /**
     * Parses a list filter with the advanced syntax.
     *
     * @param class-string<Model> $modelClass
     *
     * @throws InvalidRequest when the input is not valid
     */
    public function parseAdvancedFilter(string $input, string $modelClass, ?array $allowed = null): ListFilter
    {
        $parsed = json_decode($input, true);
        if (!is_array($parsed)) {
            throw new InvalidRequest('Invalid advanced filter: '.$input);
        }

        $allowed ??= $this->getFilterableProperties($modelClass);

        $filters = [];
        foreach ($parsed as $row) {
            $originalField = $row['field'] ?? '';
            if (!$originalField) {
                throw new InvalidRequest('Missing field in advanced filter condition');
            }

            // check if in the list of allowed filter properties
            if (!$this->isFieldAllowed($originalField, $allowed)) {
                throw new InvalidRequest("Invalid field used in advanced filter: $originalField");
            }

            // convert a belongs to relationship filter to the local key
            $property = $modelClass::definition()->get($originalField);
            $field = $originalField;
            if ($property) {
                $field = $this->getRenamedField($property, $originalField);
            }

            // determine the operator
            $operatorValue = $row['operator'] ?? '';
            if (!$operatorValue) {
                throw new InvalidRequest('Missing operator in advanced filter condition');
            }
            $operator = FilterOperator::tryFrom($operatorValue);
            if (!$operator) {
                throw new InvalidRequest('Invalid operator in advanced filter condition: '.$operatorValue);
            }

            // marshal value
            $value = $row['value'] ?? null;
            if ($property && $value) {
                $value = $this->marshalValue($property, $originalField, $value, $operator);
            }

            $filters[] = new FilterCondition(
                operator: $operator,
                field: $field,
                value: $value,
            );
        }

        return new ListFilter($filters);
    }

    private function getRenamedField(Property $property, string $field): string
    {
        if (Relationship::BELONGS_TO == $property->relation_type && $localKey = $property->local_key) {
            $field = $localKey;
        }

        return $field;
    }

    private function isFieldAllowed(string $field, array $allowed): bool
    {
        if (in_array($field, $allowed)) {
            return true;
        }

        if (!str_starts_with($field, 'metadata.')) {
            return false;
        }

        $metadataField = str_replace('metadata.', $field, '');
        if (is_numeric($metadataField) || !preg_match('/^[A-Za-z0-9_-]*$/', $metadataField)) {
            return false;
        }

        return true;
    }

    private function marshalValue(Property $property, string $field, mixed $value, FilterOperator $operator): mixed
    {
        if (Type::ENUM == $property->type) {
            return $this->marshalEnumValue($property, $field, $value);
        }

        if (Type::DATE_UNIX == $property->type) {
            return $this->marshalUnixDateValue($property, $field, $value, $operator);
        }

        return $value;
    }

    /**
     * Converts a date or datetime ISO-8601 value to the property format.
     */
    private function marshalUnixDateValue(Property $property, string $field, mixed $value, FilterOperator $operator): mixed
    {
        try {
            $date = new CarbonImmutable((string) $value);

            // Use MySQL datetime string format
            if ('timestamp|db_timestamp' == $property->validate) {
                return $date->toDateTimeString();
            }

            if (FilterOperator::GreaterThanOrEqual == $operator) {
                $date = $date->startOfDay();
            }
            if (FilterOperator::LessThanOrEqual == $operator) {
                $date = $date->endOfDay();
            }

            return $date->getTimestamp();
        } catch (Throwable) {
            throw new InvalidRequest('Invalid value for `'.$field.'` filter parameter: '.$value);
        }
    }

    /**
     * Finds the value of an enum given the name.
     */
    private function marshalEnumValue(Property $property, string $field, mixed $value): mixed
    {
        /** @var BackedEnum $enumClass */
        $enumClass = $property->enum_class;

        // Some enums have a different case name, API value, and backed value.
        // This handles that case where all 3 are different.
        if (method_exists($enumClass, 'fromString')) {
            try {
                $case = $enumClass::fromString($value); /* @phpstan-ignore-line */

                return $case->value;
            } catch (InvalidArgumentException) {
                throw new InvalidRequest('Invalid value for `'.$field.'` filter parameter: '.$value);
            }
        }

        foreach ($enumClass::cases() as $case) {
            // Check for a match based on the case name
            if ($case->name == $value) {
                return $case->value;
            }

            // Check for a match based on the case backed value
            if ($case->value == $value) {
                return $case->value;
            }
        }

        throw new InvalidRequest('Invalid value for `'.$field.'` filter parameter: '.$value);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function makeListFilter(array $simpleFilterInput, ?string $advancedFilterInput, string $modelClass, ?array $allowed = null): ListFilter
    {
        // Parse the simple filter input.
        $filter = $this->parseSimpleFilter($simpleFilterInput, $modelClass, $allowed);

        // Parse the advanced filter input if given.
        if ($advancedFilterInput) {
            $advancedFilter = $this->parseAdvancedFilter($advancedFilterInput, $modelClass, $allowed);
            $filter = new ListFilter(array_merge($filter->filters, $advancedFilter->filters));
        }

        return $filter;
    }
}
