<?php

namespace App\Metadata\Storage;

use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Interfaces\MetadataStorageInterface;
use App\Metadata\Libs\LegacyMetadataMarshaler;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use Doctrine\DBAL\Connection;
use App\Core\Orm\Model;

class LegacyMetadataStorage implements MetadataStorageInterface
{
    private const SUPPORTED_OPERATORS = [
        '=',
        '<>',
        '>=',
        '>',
        '<',
        '<=',
        'in',
        'not in',
        'like',
        'not like',
    ];

    public function __construct(private Connection $database)
    {
    }

    //
    // MetadataStorageInterface
    //

    public function save(MetadataModelInterface $model, object $metadata, bool $isUpdate): void
    {
        if (!$model instanceof Model) {
            return;
        }

        if ($isUpdate) {
            $this->delete($model);
        }

        // insert each key-value pair into the metadata store
        $marshaler = new LegacyMetadataMarshaler($model->tenant());
        $metadataToInsert = [];
        $typeName = $model->getObjectName();
        foreach ((array) $metadata as $key => $value) {
            $value = $marshaler->castToStorage($typeName, $key, $value);

            $metadataToInsert[] = [
                'tenantId' => $model->tenant_id,
                'objectType' => $typeName,
                'objectId' => $model->id(),
                'key' => $key,
                'value' => $value,
            ];
        }

        $sql = 'INSERT INTO Metadata (tenant_id, object_type, object_id, `key`, `value`) VALUES (:tenantId, :objectType, :objectId, :key, :value) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';
        foreach ($metadataToInsert as $row) {
            $this->database->executeStatement($sql, $row);
        }
    }

    public function retrieve(MetadataModelInterface $model): object
    {
        $typeName = $model->getObjectName();
        $data = $this->database->createQueryBuilder()
            ->select('`key`,`value`')
            ->from('Metadata')
            ->where('tenant_id = :tenantId')
            ->setParameter('tenantId', $model->tenant_id) /* @phpstan-ignore-line */
            ->andWhere('object_type = "'.$typeName.'"')
            ->andWhere('object_id = :objectId')
            ->setParameter('objectId', $model->id())
            ->fetchAllAssociative();

        $metadata = new \stdClass();
        $caster = new LegacyMetadataMarshaler($model->tenant()); /* @phpstan-ignore-line */

        foreach ($data as $row) {
            $k = $row['key'];

            // type cast the metadata value from storage
            $metadata->$k = $caster->castFromStorage($typeName, $k, $row['value']);
        }

        return $metadata;
    }

    public function delete(MetadataModelInterface $model): void
    {
        $this->database->delete('Metadata', [
            'tenant_id' => $model->tenant_id, /* @phpstan-ignore-line */
            'object_type' => $model->getObjectName(),
            'object_id' => $model->id(),
        ]);
    }

    public function buildSqlConditions(array $conditions, MetadataModelInterface $model, int $tenantId, ?string $idColumn = null): array
    {
        $objectType = $model->getObjectName();
        $ids = $model::definition()->getIds();
        $idProperty = $ids[0];
        $idColumn ??= $model->getTablename().'.'.$idProperty;

        $result = [];
        foreach ($conditions as $condition) {
            $result[] = $this->buildSqlCondition($condition, $objectType, $tenantId, $idColumn);
        }

        return $result;
    }

    /**
     * @throws MetadataStorageException
     */
    private function buildSqlCondition(MetadataQueryCondition $condition, string $objectType, int $tenantId, string $idColumn): string
    {
        $k = $this->database->quote($condition->attributeName);

        $operator = $condition->operator;
        if (in_array($operator, ['IN', 'NOT IN'])) {
            if (!is_array($condition->value)) {
                throw new MetadataStorageException('Metadata condition value type must be array');
            }

            $values = [];
            foreach ($condition->value as $subValue) {
                $values[] = $this->database->quote($subValue);
            }

            if (1 == count($values)) {
                $operator = 'NOT IN' == $operator ? '<>' : '=';
                $value = $values[0];
            } else {
                $value = '('.implode(',', $values).')';
            }
        } elseif (null === $condition->value) {
            $value = null;
        } else {
            $value = $this->database->quote($condition->value);
        }

        if (!in_array(strtolower($operator), self::SUPPORTED_OPERATORS)) {
            throw new MetadataStorageException('This custom field type does not support query operator: '.$operator);
        }

        if (null === $value && in_array($operator, ['=', '<>'])) {
            return '(SELECT 1 FROM Metadata WHERE `tenant_id`='.$tenantId.' AND `object_type`="'.$objectType.'" AND object_id='.$idColumn.' AND `key`='.$k.') IS'.('<>' == $operator ? ' NOT' : '').' NULL';
        }

        return 'EXISTS (SELECT 1 FROM Metadata WHERE `tenant_id`='.$tenantId.' AND `object_type`="'.$objectType.'" AND object_id='.$idColumn.' AND `key`='.$k.' AND `value` '.$operator.' '.$value.')';
    }
}
