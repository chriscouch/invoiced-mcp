<?php

namespace App\Exports\Exporters;

use App\Companies\Models\Company;
use App\Core\Csv\CsvWriter;
use App\Core\Orm\Model;
use App\Core\Orm\Query;
use App\Core\Orm\Type;
use App\Core\Utils\Enums\ObjectType;
use App\Exports\Models\Export;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Storage\AttributeStorage;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use mikehaertl\tmp\File;
use RuntimeException;

/**
 * @template T of Model
 */
abstract class AbstractCsvExporter extends AbstractExporter
{
    protected Company $company;

    /**
     * @return Query<T>
     */
    abstract protected function getQuery(array $options): Query;

    /**
     * Gets the columns exported for each object.
     */
    abstract protected function getColumns(): array;

    protected function getFileName(Export $export): string
    {
        return parent::getFileName($export).'.csv';
    }

    public function build(Export $export, array $options): void
    {
        $this->company = $export->tenant();
        $models = $this->getQuery($options)->all();

        // save the total # of records
        $export->incrementTotalRecords(count($models));

        $temp = new File('', 'csv');
        $tempFileName = $temp->getFileName();
        $fp = fopen($tempFileName, 'w');
        if (!$fp) {
            throw new RuntimeException('Could not open temp file');
        }

        $columns = $options['columns'] ?? $this->getColumns();
        $header = [];
        foreach ($columns as $field) {
            $header[] = $this->getCsvColumnLabel($field);
        }

        CsvWriter::write($fp, $header);

        foreach ($models as $model) {
            // convert each model to one or more CSV rows
            foreach ($this->buildCsvRows($model, $columns) as $row) {
                CsvWriter::write($fp, $row);
            }

            // update position
            $export->incrementPosition();
        }

        fclose($fp);

        $this->persist($export, $tempFileName);
        $this->finish($export);
    }

    protected function getCsvColumnLabel(string $field): string
    {
        return $field;
    }

    protected function isLineItemColumn(string $column): bool
    {
        return false;
    }

    protected function buildCsvRows(Model $model, array $columns): array
    {
        // If there are model line items returned then we generate a row
        // for each item that belongs to the model. This results in 1 or
        // more rows being returned.
        // If not then a single row is generated for the model.
        $modelItems = $this->getCsvModelItems($model);

        $lines = [];
        if (is_array($modelItems)) {
            foreach ($modelItems as $i => $modelItem) {
                $isFirst = 0 == $i;
                $lines[] = $this->buildCsvRow($model, $columns, $modelItem, $isFirst);
            }
        } else {
            $lines[] = $this->buildCsvRow($model, $columns, null, true);
        }

        return $lines;
    }

    /**
     * Gets the list of sub-rows to process for a given model.
     */
    protected function getCsvModelItems(Model $model): ?array
    {
        return null;
    }

    protected function buildCsvRow(Model $model, array $columns, mixed $item, bool $isFirst): array
    {
        $line = [];
        foreach ($columns as $j => $column) {
            // Only put non-line item values on the first row for this model
            if ($this->isLineItemColumn($column)) {
                $line[$j] = $this->getCsvLineItemColumnValue($model, $column, $item);
            } elseif ($isFirst) {
                $line[$j] = $this->getCsvColumnValue($model, $column);
            } else {
                $line[$j] = '';
            }
        }

        return $line;
    }

    /**
     * Gets the CSV value for a model property.
     */
    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if (str_starts_with($field, 'metadata.')) {
            $key = str_replace('metadata.', '', $field);
            $metadata = $model->metadata;

            return (string) ($metadata->$key ?? '');
        }

        $property = $model::definition()->get($field);
        $type = $property?->type ?? Type::STRING;

        $value = $model->{$field};
        if (Type::BOOLEAN === $type) {
            return $value ? '1' : '0';
        }

        if (Type::DATE_UNIX === $type && $value) {
            return CarbonImmutable::createFromTimestamp($value)->toDateString();
        }

        if (Type::DATE === $type && $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (Type::DATETIME === $type && $value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (Type::OBJECT === $type || Type::ARRAY === $type) {
            return (string) json_encode($value);
        }

        if (Type::ENUM === $type && $value instanceof BackedEnum) {
            // Some enums have a different case name, API value, and backed value.
            // This handles that case where all 3 are different.
            if (method_exists($value, 'toString')) {
                return $value->toString();
            }

            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * This could be overriden if the exporter supports line items.
     */
    protected function getCsvLineItemColumnValue(Model $model, string $field, mixed $item): string
    {
        if ($item instanceof Model) {
            return $this->getCsvColumnValue($item, $field);
        }

        return '';
    }

    /**
     * Gets a list of all possible metadata columns for a given object type.
     */
    protected function getMetadataColumns(ObjectType $objectType): array
    {
        $modelClass = $objectType->modelClass();
        /** @var MetadataModelInterface $model */
        $model = new $modelClass();
        $reader = $model->getMetadataReader();
        // EAV metadata
        if ($reader instanceof AttributeStorage) {
            $this->attributeHelper->build($model);
            $attributes = $this->attributeHelper->getAllAttributes();

            $result = [];
            foreach ($attributes as $attribute) {
                $result[] = 'metadata.'.$attribute->getName();
            }

            return $result;
        }

        // Legacy metadata
        $columns = $this->database->createQueryBuilder()
            ->select('`key`')
            ->from('Metadata')
            ->andWhere('tenant_id = :tenantId')
            ->setParameter('tenantId', $this->company->id())
            ->andWhere('object_type = :objectType')
            ->setParameter('objectType', $objectType->typeName())
            ->groupBy('`key`')
            ->orderBy('`key`', 'ASC')
            ->fetchFirstColumn();
        $result = [];
        foreach ($columns as $column) {
            $result[] = 'metadata.'.$column;
        }

        return $result;
    }
}
