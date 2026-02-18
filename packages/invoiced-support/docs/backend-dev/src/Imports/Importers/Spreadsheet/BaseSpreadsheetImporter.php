<?php

namespace App\Imports\Importers\Spreadsheet;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\Orm\Model;
use App\Core\Utils\Enums\ObjectType;
use App\Imports\Exceptions\RecordException;
use App\Imports\Exceptions\ValidationException;
use App\Imports\Importers\BaseFileImporter;
use App\Imports\Libs\ImportConfiguration;
use App\Imports\Libs\ImportHelper;
use App\Imports\Models\Import;
use App\Imports\ValueObjects\ImportRecordResult;

abstract class BaseSpreadsheetImporter extends BaseFileImporter
{
    protected const CREATE = 'create';
    protected const UPDATE = 'update';
    protected const UPSERT = 'upsert';
    protected const DELETE = 'delete';
    protected const VOID = 'void';

    private array $fieldConfig;

    private array $models = [];

    /**
     * This can be called by any operation that needs to locate an existing record.
     *
     * @throws RecordException
     */
    abstract protected function findExistingRecord(array $record): ?Model;

    public function getName(string $type, array $options): string
    {
        return ImportConfiguration::get()->getName($type);
    }

    public function build(array $mapping, array $lines, array $options, Import $import): array
    {
        $this->models = []; // reset model cache

        $options['operation'] ??= self::CREATE;

        $data = [];
        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                continue;
            }

            try {
                $data[] = $this->buildRecord($mapping, $line, $options, $import);
            } catch (ValidationException $e) {
                // decorate exception with
                // line number/record and rethrow
                $e->setLineNumber($i + 2)
                    ->setRecord($line);

                throw $e;
            }
        }

        return $data;
    }

    /**
     * @throws ValidationException
     */
    public function buildRecord(array $mapping, array $line, array $options, Import $import): array
    {
        // map values from columns to properties
        $fieldConfig = $this->getFieldConfig($import->type);
        $record = ImportHelper::mapRecord($mapping, $line, $fieldConfig['allowed']);

        // set the record operation
        $record['_operation'] = strtolower($record['_operation'] ?? '');
        if (!$record['_operation']) {
            $record['_operation'] = $options['operation'];
        }

        // check for supported operation
        if (!in_array($record['_operation'], $fieldConfig['supportedOperations'])) {
            throw new ValidationException('Unsupported '.$import->type.' operation: '.$record['_operation']);
        }

        // build customer
        if ($fieldConfig['customer']) {
            $customer = ImportHelper::mapCustomerProfile($import->tenant(), $record, $fieldConfig['customer']);
            $record = ImportHelper::withoutCustomerProperties($record, $fieldConfig['customer']);
            $record['customer'] = $customer;
            if (0 === count($record['customer'])) {
                unset($record['customer']);
            }
        }

        // parse numbers
        foreach ($fieldConfig['floats'] as $k) {
            if (ImportHelper::cellHasValue($record, $k)) {
                $record[$k] = ImportHelper::parseFloat($record[$k]);
            }
        }
        foreach ($fieldConfig['ints'] as $k) {
            if (ImportHelper::cellHasValue($record, $k)) {
                $record[$k] = ImportHelper::parseInt($record[$k]);
            }
        }

        // parse dates
        foreach ($fieldConfig['dates'] as $k) {
            if (isset($record[$k])) {
                $endOfDay = 'due_date' == $k;
                $record[$k] = ImportHelper::parseDateUnixTimestamp($record[$k], $import->tenant()->country, $endOfDay);
            }
        }

        foreach ($fieldConfig['isoDates'] as $k) {
            if (isset($record[$k])) {
                $record[$k] = ImportHelper::parseDate($record[$k], $import->tenant()->country);
            }
        }

        // parse booleans
        foreach ($fieldConfig['booleans'] as $k) {
            if (ImportHelper::cellHasValue($record, $k)) {
                $record[$k] = ImportHelper::parseBoolean($record[$k]);
            }
        }

        // parse relationships
        foreach ($fieldConfig['relationships'] as $k => $relationship) {
            $objectType = $relationship['object'] ?? '';
            $class = ObjectType::fromTypeName($objectType)->modelClass();
            $fields = $relationship['fields'] ?? null;
            if (!$fields) {
                throw new ValidationException('Invalid import field configuration');
            }

            if (!array_key_exists($k, $record)) {
                continue;
            } elseif (null == $record[$k]) {
                // Explicitly set to null because the condition may return true if the value
                // is an integer (0) or string ('').
                $record[$k] = null;
                continue;
            }

            // Update value to model
            $value = $record[$k];
            foreach ($fields as $field) {
                $cached = $this->models[$objectType][$field][$value] ?? null;
                if ($cached instanceof Model) {
                    $record[$k] = $cached;
                    break;
                } elseif ($models = $class::where($field, $value)->first(2)) {
                    if (1 != count($models)) {
                        throw new ValidationException("More than one $objectType was found.");
                    }

                    $model = $models[0];
                    $this->models[$objectType][$field][$value] = $model;
                    $record[$k] = $model;
                    break;
                }
            }

            // check that model was found
            if (!($record[$k] instanceof Model)) {
                throw new ValidationException("No $objectType was found.");
            }
        }

        return $record;
    }

    public function importRecord(array $record, array $options): ImportRecordResult
    {
        // Invoke the appropriate import operation. At this point
        // the requested operation has already been validated to
        // be supported per the configuration for this data type.
        $operation = $record['_operation'];
        unset($record['_operation']);

        $existingRecord = $this->findExistingRecord($record);

        if (self::CREATE == $operation) {
            // Skip creating if there IS an existing record
            if ($existingRecord) {
                return new ImportRecordResult();
            }
            $record = $this->beforeCreateRecord($record);

            return $this->createRecord($record);
        }

        if (self::UPDATE == $operation) {
            // Skip the update if there IS NOT an existing record
            if (!$existingRecord) {
                return new ImportRecordResult();
            }

            return $this->updateRecord($record, $existingRecord);
        }

        if (self::UPSERT == $operation) {
            return $this->upsertRecord($record, $existingRecord);
        }

        if (self::VOID == $operation) {
            // Skip the void if there IS NOT an existing record
            if (!$existingRecord) {
                return new ImportRecordResult();
            }

            return $this->voidRecord($record, $existingRecord);
        }

        if (self::DELETE == $operation) {
            // Skip deleting if there IS NOT an existing record
            if (!$existingRecord) {
                return new ImportRecordResult();
            }

            return $this->deleteRecord($record, $existingRecord);
        }

        throw new RecordException('Operation not recognized: '.$operation);
    }

    /**
     * Performs a create operation. If the import type supports this operation
     * then it MUST override this method.
     *
     * @throws RecordException
     */
    protected function createRecord(array $record): ImportRecordResult
    {
        throw new RecordException('Create operation not implemented');
    }

    /**
     * Performs an update operation. If the import type supports this operation
     * then it MUST override this method.
     *
     * @throws RecordException
     */
    protected function updateRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        throw new RecordException('Update operation not implemented');
    }

    /**
     * Performs an upsert operation. If the import type supports this operation
     * then it MUST override the createRecord() and updateRecord() methods.
     *
     * @throws RecordException
     */
    protected function upsertRecord(array $record, ?Model $existingRecord): ImportRecordResult
    {
        if ($existingRecord) {
            return $this->updateRecord($record, $existingRecord);
        }
        $record = $this->beforeCreateRecord($record);

        return $this->createRecord($record);
    }

    private function beforeCreateRecord(array $record): array
    {
        if (isset($record['metadata'])) {
            $record['metadata'] = $this->cleanMetadata($record['metadata']);
        }

        return $record;
    }

    private function cleanMetadata(array|object $metadata): object
    {
        return (object) array_filter((array) $metadata, fn ($item) => !is_null($item));
    }

    /**
     * Performs a void operation. If the import type supports this operation
     * then it MUST override this method.
     *
     * @throws RecordException
     */
    protected function voidRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        throw new RecordException('Void operation not implemented');
    }

    /**
     * Performs a delete operation. If the import type supports this operation
     * then it MUST override this method.
     *
     * @throws RecordException
     */
    protected function deleteRecord(array $record, Model $existingRecord): ImportRecordResult
    {
        throw new RecordException('Delete operation not implemented');
    }

    private function getFieldConfig(string $type): array
    {
        if (!isset($this->fieldConfig)) {
            $configuration = ImportConfiguration::get();
            $fields = $configuration->getFields($type);
            $config = [
                'allowed' => ['_operation'],
                'customer' => $configuration->getCustomerFields($type),
                'floats' => [],
                'ints' => [],
                'dates' => [],
                'isoDates' => [],
                'booleans' => [],
                'relationships' => [],
                'supportedOperations' => $configuration->getSupportedOperations($type),
            ];

            foreach ($fields as $id => $field) {
                $config['allowed'][] = $id;
                if ('float' == $field['type'] || 'money' == $field['type']) {
                    $config['floats'][] = $id;
                } elseif ('integer' == $field['type']) {
                    $config['ints'][] = $id;
                } elseif ('date' == $field['type'] && isset($field['date_format'])) {
                    $config['isoDates'][] = $id;
                } elseif ('date' == $field['type']) {
                    $config['dates'][] = $id;
                } elseif ('boolean' == $field['type']) {
                    $config['booleans'][] = $id;
                }

                if (isset($field['relationship'])) {
                    $config['relationships'][$id] = $field['relationship'];
                }
            }

            $this->fieldConfig = $config;
        }

        return $this->fieldConfig;
    }

    protected function updateExistingRecord(ReceivableDocument|Customer $existingRecord, string $k, mixed $v): void
    {
        // Make sure that existing metadata not included
        // in the import is not overwritten.
        if ('metadata' == $k) {
            $v = $this->cleanMetadata(array_merge((array) $existingRecord->metadata, (array) $v));
        }

        // never clear out the number field on update
        if ('number' == $k && !$v) {
            return;
        }

        if ($v === '')
            $v = null;

        $existingRecord->$k = $v;
    }
}
