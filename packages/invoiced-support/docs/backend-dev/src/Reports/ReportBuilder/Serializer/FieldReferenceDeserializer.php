<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ReportConfiguration;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use App\Reports\ReportBuilder\ValueObjects\JoinCondition;
use App\Reports\ReportBuilder\ValueObjects\Table;

/**
 * Responsible for deserializing field references. A field reference
 * maps to a select column given an object / field.
 */
final class FieldReferenceDeserializer
{
    public static function deserialize(string $object, string $id, JoinCollector $joins, Company $company): FieldReferenceExpression
    {
        // check if the object exists
        $configuration = ReportConfiguration::get();
        if (!$configuration->hasObject($object)) {
            throw new ReportException("Unrecognized object type: $object");
        }

        // resolve field reference
        [$table, $id, $metadataObject] = self::resolveField($object, $id, $joins);

        // lookup the field on the object
        $object = $table->object;
        if (!$configuration->hasField($object, $id)) {
            throw new ReportException("Unrecognized reporting field: $object.$id");
        }

        $fieldMetadata = $configuration->getField($object, $id, $metadataObject, $company);

        return new FieldReferenceExpression(
            table: $table,
            id: $id,
            type: ColumnType::tryFrom($fieldMetadata['type']),
            name: $fieldMetadata['name'],
            metadataObject: $metadataObject,
            shouldSummarize: $fieldMetadata['summarize'] ?? false,
            dateFormat: $fieldMetadata['date_format'] ?? 'U'
        );
    }

    /**
     * @throws ReportException
     */
    private static function resolveField(string $object, string $id, JoinCollector $joins): array
    {
        if (!str_contains($id, '.')) {
            return [new Table($object), $id, null];
        }

        $joinFields = explode('.', $id);
        $id = $joinFields[count($joinFields) - 1];
        unset($joinFields[count($joinFields) - 1]);
        $path = implode('.', $joinFields);

        $configuration = ReportConfiguration::get();
        $parentObject = $object;
        $joinPathEls = [];
        foreach ($joinFields as $joinField) {
            if ('metadata' == $joinField) {
                $parentObject = $joinField;
                continue;
            }

            if (!$configuration->hasJoin($parentObject, $joinField)) {
                throw new ReportException("The $joinField field does not exist on the $parentObject object.");
            }

            $joinParams = $configuration->getJoin($parentObject, $joinField);
            $joinObject = $joinParams['join_object'] ?? $joinField;
            $parentJoinPath = implode('.', $joinPathEls);
            $joinPathEls[] = $joinField;
            $joinPath = implode('.', $joinPathEls);
            $join = new JoinCondition(new Table($parentObject, $parentJoinPath), new Table($joinObject, $joinPath), $joinParams);
            $joins->add($join);
            $parentObject = $joinObject;
        }

        // Add metadata's parent object if this is a metadata field
        $metadataObject = null;
        if ('metadata' == $parentObject) {
            if (count($joinFields) > 1) {
                $metadataObject = $joinFields[count($joinFields) - 2];
            } else {
                $metadataObject = $object;
            }

            $pathFields = array_merge(array_slice($joinFields, 0, -2), [$metadataObject]);
            $path = implode('.', $pathFields);
        }

        return [new Table($parentObject, $path), $id, $metadataObject];
    }
}
