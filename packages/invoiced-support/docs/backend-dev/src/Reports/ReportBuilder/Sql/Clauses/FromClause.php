<?php

namespace App\Reports\ReportBuilder\Sql\Clauses;

use App\Core\Utils\Enums\ObjectType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ReportConfiguration;
use App\Reports\ReportBuilder\Interfaces\ClauseInterface;
use App\Reports\ReportBuilder\Interfaces\VirtualTableInterface;
use App\Reports\ReportBuilder\Sql\VirtualTables\SaleLineItemTable;
use App\Reports\ReportBuilder\Sql\VirtualTables\SalesTable;
use App\Reports\ReportBuilder\ValueObjects\DataQuery;
use App\Reports\ReportBuilder\ValueObjects\SqlContext;
use App\Reports\ReportBuilder\ValueObjects\Table;
use App\Core\Orm\Model;
use RuntimeException;

final class FromClause implements ClauseInterface
{
    const VIRTUAL_TABLES = [
        'sale' => SalesTable::class,
        'sale_line_item' => SaleLineItemTable::class,
    ];

    private static array $tablenames = [];

    public static function makeSql(DataQuery $query, SqlContext $context): string
    {
        // Derived tables require custom SQL
        $table = $query->table;
        $objectType = $table->object;
        if (isset(self::VIRTUAL_TABLES[$objectType])) {
            /** @var VirtualTableInterface $virtualTable */
            $virtualTable = self::VIRTUAL_TABLES[$objectType];
            $tablename = $virtualTable::makeSql($query, $context);
        } else {
            $tablename = self::getTablename($table);
        }

        return 'FROM '.$tablename.' '.$context->getTableAlias($table);
    }

    /**
     * @throws ReportException
     */
    public static function getTablename(Table $table): string
    {
        $objectType = $table->object;
        if (isset(self::$tablenames[$objectType])) {
            return self::$tablenames[$objectType];
        }

        $objectConfiguration = ReportConfiguration::get()->getObject($objectType);
        if (isset($objectConfiguration['tablename'])) {
            self::$tablenames[$objectType] = $objectConfiguration['tablename'];
        } else {
            try {
                /** @var Model $model */
                $model = ObjectType::fromTypeName($objectType)->modelClass();
            } catch (RuntimeException $e) {
                throw new ReportException($e->getMessage());
            }
            self::$tablenames[$objectType] = (new $model())->getTablename();
        }

        return self::$tablenames[$objectType];
    }
}
