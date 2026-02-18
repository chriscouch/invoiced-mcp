<?php

namespace App\Metadata\Libs;

use App\Companies\Models\Company;
use App\Core\Multitenant\TenantContext;
use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Models\CustomField;
use App\Metadata\ValueObjects\Attribute;
use App\Metadata\ValueObjects\AttributeBoolean;
use App\Metadata\ValueObjects\AttributeDecimal;
use App\Metadata\ValueObjects\AttributeMoney;
use App\Metadata\ValueObjects\AttributeString;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

class AttributeHelper
{
    const TYPE_STRING = 1;
    const TYPE_BOOLEAN = 2;
    const TYPE_DECIMAL = 3;
    const TYPE_MONEY = 4;

    private MetadataModelInterface $model;
    private string $attributeTable;
    private string $customFieldType;
    private ?int $customTenant = null;

    public function __construct(
        private Connection $database,
        private TenantContext $tenant,
    ) {
    }

    public function build(MetadataModelInterface $model, ?int $tenantId = null): void
    {
        $this->model = $model;
        $prefix = $model->getMetadataTablePrefix();
        $this->attributeTable = $prefix.'Attributes';
        $this->customFieldType = $model->getObjectName();
        $this->customTenant = $tenantId;
    }

    private function getCompany(): Company
    {
        if ($this->customTenant) {
            if ($this->tenant->has() && $this->tenant->get()->id == $this->customTenant) {
                return $this->tenant->get();
            }

            return new Company(['id' => $this->customTenant]);
        }

        return $this->tenant->get();
    }

    /**
     * @return Attribute[]
     */
    private function getAttributeClasses(): array
    {
        $classes = [
            new AttributeMoney(),
            new AttributeBoolean(),
            new AttributeDecimal(),
            new AttributeString(),
        ];
        foreach ($classes as $class) {
            $class->setModel($this->model);
        }

        return $classes;
    }

    public function clean(): void
    {
        $id = $this->model->id();
        $attributeClasses = $this->getAttributeClasses();

        foreach ($attributeClasses as $attributeClass) {
            $this->database->delete($attributeClass->getTable(), ['object_id' => $id]);
        }
    }

    /**
     * @param string[] $attributeNames
     *
     * @return Attribute[]
     */
    public function getAttributes(array $attributeNames): array
    {
        if (0 == count($attributeNames)) {
            return [];
        }

        $tenant = $this->getCompany();
        $tenantId = $tenant->id;
        $attributeVars = implode(',', array_fill(0, count($attributeNames), '?'));

        $sql = 'SELECT id,name,`type` FROM '.$this->attributeTable.' WHERE tenant_id = ? AND name IN ('.$attributeVars.')';
        $sqlParams = array_merge([$tenantId], $attributeNames);
        $result = $this->database->fetchAllAssociative($sql, $sqlParams);

        $attributes = [];
        foreach ($result as $row) {
            $attributes[$row['name']] = $this->buildAttribute($row['id'], $row['name'], $row['type']);
        }

        return $attributes;
    }

    /**
     * @return Attribute[]
     */
    public function getAllAttributes(): array
    {
        $sql = 'SELECT id,name,`type` FROM '.$this->attributeTable.' WHERE tenant_id = ?';
        $result = $this->database->fetchAllAssociative($sql, [
            $this->getCompany()->id,
        ]);

        $attributes = [];
        foreach ($result as $row) {
            $attributes[$row['name']] = $this->buildAttribute($row['id'], $row['name'], $row['type']);
        }

        return $attributes;
    }

    public function getCustomField(string $name): ?CustomField
    {
        $tenant = $this->getCompany();

        return CustomFieldRepository::get($tenant)->getCustomField($this->customFieldType, $name);
    }

    /**
     * @throws MetadataStorageException
     * @throws Exception
     */
    public function setAttributes(array $metadata): void
    {
        $tenant = $this->getCompany();

        $attributes = $this->getAttributes(array_keys($metadata));

        foreach ($metadata as $name => $value) {
            // we create new attribute if current one is not set
            if (isset($attributes[$name])) {
                $attribute = $attributes[$name];
            } else {
                $customField = $this->getCustomField($name);
                $type = $customField
                    ? $this->customFieldToAttributeType($customField)
                    : self::TYPE_STRING;
                $sql = 'INSERT INTO '.$this->attributeTable.' (tenant_id, name, `type`) VALUES (:tenantId, :name, :type)';
                $sqlParams = [
                    'tenantId' => $tenant->id,
                    'name' => $name,
                    'type' => $type,
                ];

                $this->database->executeQuery($sql, $sqlParams);

                $id = $this->database->lastInsertId();
                if (!$id || !is_numeric($id)) {
                    throw new MetadataStorageException("Couldn't save metadata attribute");
                }
                $attribute = $this->buildAttribute((int) $id, $name, $type);
            }

            $attribute->setModel($this->model);
            $attribute->setValue($value);
            $this->database->executeQuery($attribute->getInsertSQL(), $attribute->getInsertParameters());
        }
    }

    private function buildAttribute(int $id, string $name, int $type): Attribute
    {
        $attribute = $this->getAttributeByType($type);
        $attribute->set($id, $name, $type);

        return $attribute;
    }

    public function getAttributeByType(int $type): Attribute
    {
        return match ($type) {
            self::TYPE_MONEY => new AttributeMoney(),
            self::TYPE_DECIMAL => new AttributeDecimal(),
            self::TYPE_BOOLEAN => new AttributeBoolean(),
            self::TYPE_STRING => new AttributeString(),
            default => throw new MetadataStorageException('Unsupported Attribute Type'),
        };
    }

    private function customFieldToAttributeType(CustomField $cf): int
    {
        return match ($cf->type) {
            CustomField::FIELD_TYPE_STRING, CustomField::FIELD_TYPE_ENUM, CustomField::FIELD_TYPE_DATE => self::TYPE_STRING,
            CustomField::FIELD_TYPE_BOOLEAN => self::TYPE_BOOLEAN,
            CustomField::FIELD_TYPE_DOUBLE => self::TYPE_DECIMAL,
            CustomField::FIELD_TYPE_MONEY => self::TYPE_MONEY,
            default => throw new MetadataStorageException('Invalid Custom Field Type'),
        };
    }

    public function get(): object
    {
        $metadata = new \stdClass();
        $model = $this->model;

        $attributeClasses = $this->getAttributeClasses();

        foreach ($attributeClasses as $attributeClass) {
            $rows = $this->database->fetchAllAssociative($attributeClass->getSelectSQL(), [$model->id()]);
            foreach ($rows as $data) {
                $item = $attributeClass->format($data);
                $metadata->{$item['key']} = $item['value'];
            }
        }

        return $metadata;
    }

    /**
     * @param MetadataQueryCondition[] $conditions
     * @param Attribute[]              $attributes
     *
     * @return string[]
     */
    public function buildWhereCondition(array $conditions, array $attributes, ?string $idColumn): array
    {
        $model = $this->model;
        $ids = $model::definition()->getIds();
        $idProperty = $ids[0];
        $idColumn ??= $model->getTablename().'.'.$idProperty;

        // Add an AND where condition for each condition
        $result = [];
        foreach ($conditions as $condition) {
            $attribute = $attributes[$condition->attributeName];
            $attribute->setModel($model);
            if (in_array($condition->operator, ['IN', 'NOT IN'])) {
                if (!is_array($condition->value)) {
                    throw new MetadataStorageException('Metadata condition value type must be array');
                }

                $fragments = $attribute->getWhereConditionsIn($condition->value, $condition->operator);
            } else {
                $attribute->setValue($condition->value);
                $fragments = $attribute->getWhereConditions($condition->operator);
            }

            $whereConditions = [];
            foreach ($fragments as $fragment) {
                [$column, $conditionOperator, $conditionValue] = $fragment;
                if (is_string($conditionValue)) {
                    $conditionValue = $this->database->quote($conditionValue);
                } elseif (is_array($conditionValue)) {
                    $inCondition = [];
                    foreach ($conditionValue as $value) {
                        if (is_string($value)) {
                            $value = $this->database->quote($value);
                        }
                        $inCondition[] = $value;
                    }
                    $conditionValue = '('.implode(',', $inCondition).')';
                }

                $whereConditions[] = $column.' '.$conditionOperator.' '.$conditionValue;
            }

            $where = implode(' AND ', $whereConditions);

            $result[] = 'EXISTS (SELECT 1 FROM '.$attribute->getTable().' WHERE object_id='.$idColumn.' AND attribute_id='.$attribute->getId().' AND '.$where.')';
        }

        return $result;
    }
}
