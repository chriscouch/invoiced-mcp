<?php

namespace App\Metadata\Storage;

use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Interfaces\MetadataStorageInterface;
use App\Metadata\Libs\AttributeHelper;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Responsible for storing and retrieving metadata
 * attributes from the database.
 */
class AttributeStorage implements LoggerAwareInterface, MetadataStorageInterface
{
    use LoggerAwareTrait;

    public function __construct(private AttributeHelper $helper)
    {
    }

    //
    // MetadataStorageInterface
    //

    public function save(MetadataModelInterface $model, object $metadata, bool $isUpdate): void
    {
        $this->helper->build($model);

        try {
            // clear any existing values
            if ($isUpdate) {
                $this->helper->clean();
            }
            // look up the existing attributes
            $metadata = (array) $metadata;
            if ($metadata) {
                $this->helper->setAttributes($metadata);
            }
        } catch (MetadataStorageException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Could not save metadata', ['exception' => $e]);

            throw new MetadataStorageException('Could not save metadata');
        }
    }

    public function retrieve(MetadataModelInterface $model): object
    {
        $this->helper->build($model);

        return $this->helper->get();
    }

    public function delete(MetadataModelInterface $model): void
    {
        // this method does nothing because the EAV storage already
        // handles cleaning up deleted models with foreign keys
    }

    public function buildSqlConditions(array $conditions, MetadataModelInterface $model, int $tenantId, ?string $idColumn = null): array
    {
        $this->helper->build($model, $tenantId);
        // retrieve the attributes for the attribute names provided in the filter
        $attributeNames = [];
        foreach ($conditions as $condition) {
            $attributeNames[] = $condition->attributeName;
        }
        $attributes = $this->helper->getAttributes(array_unique($attributeNames));

        // Check that all attributes were located.
        // If an attribute does not exist then we can assume the
        // query will return no results. Since we don't have a valid
        // attribute ID we will add a condition that will ensure
        // the query returns 0 results.
        if (count($attributes) != count($attributeNames)) {
            return ['1 = 2'];
        }

        return $this->helper->buildWhereCondition($conditions, $attributes, $idColumn);
    }

    /**
     * @return string - sql query
     */
    public function getMetadataQuery(MetadataModelInterface $model, string $customFieldRefId, string $alias): string
    {
        $this->helper->build($model);
        $attribute = null;
        $customField = $this->helper->getCustomField($customFieldRefId);
        if ($customField) {
            $attribute = current($this->helper->getAttributes([$customField->id]));
        }
        if (!$attribute) {
            return '(1=2)';
        }
        $attribute->setModel($model);
        $table = $attribute->getTable();
        $ids = $model::definition()->getIds();
        $idColumn = $ids[0];

        return '('.
            'SELECT `value` '.
            "FROM $table ".
            'WHERE `attribute_id`="'.$attribute->getId().'" AND object_id='.$alias.'.'.$idColumn.
            ')';
    }
}
