<?php

namespace App\Reports\ReportBuilder\Sql\VirtualColumns;

use App\Core\Utils\Enums\ObjectType;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Storage\AttributeStorage;
use App\Reports\ReportBuilder\Interfaces\VirtualColumnInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Core\Orm\Model;
use RuntimeException;

final class MetadataColumn implements VirtualColumnInterface
{
    private const VIRTUAL_OBJECT_TYPE_QUERY = [
        'credit_note_line_item' => 'object_type="line_item"',
        'estimate_line_item' => 'object_type="line_item"',
        'invoice_line_item' => 'object_type="line_item"',
        'pending_line_item' => 'object_type="line_item"',
        'sale' => 'object_type IN ("credit_note", "invoice")',
        'sale_line_item' => 'object_type="line_item"',
    ];

    /**
     * Handles metadata columns which require
     * a more sophisticated query to select.
     */
    public static function makeSql(FieldReferenceExpression $fieldReference, SqlContext $context): string
    {
        $object = (string) $fieldReference->metadataObject;
        $idColumn = 'id';

        try {
            $modelClass = ObjectType::fromTypeName($object)->modelClass();
            /** @var Model $model */
            $model = new $modelClass();
            if ($model instanceof MetadataModelInterface) {
                $reader = $model->getMetadataReader();
                if ($reader instanceof AttributeStorage) {
                    return $reader->getMetadataQuery($model, $fieldReference->id, $context->getTableAlias($fieldReference->table));
                }
            }

            $ids = $model::definition()->getIds();
            $idColumn = $ids[0];
        } catch (RuntimeException) {
            // do nothing if there is not a corresponding model
        }

        $tableAlias = $context->getTableAlias($fieldReference->table);
        $objectTypeQuery = self::VIRTUAL_OBJECT_TYPE_QUERY[$object] ?? 'object_type="'.$object.'"';

        $qry = '('.
            'SELECT `value` '.
            'FROM Metadata '.
            'WHERE tenant_id='.$tableAlias.'.tenant_id AND `key`="'.$fieldReference->id.'" AND '.$objectTypeQuery.' AND object_id='.$tableAlias.'.'.$idColumn
        ;

        if ($value = $fieldReference->queryValue()) {
            $qry .= ' AND value=? ';
            $context->addParam($value);
        }

        return $qry.')';
    }
}
