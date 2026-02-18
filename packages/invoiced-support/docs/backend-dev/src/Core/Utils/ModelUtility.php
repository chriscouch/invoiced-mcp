<?php

namespace App\Core\Utils;

use Generator;
use App\Core\Orm\Model;
use App\Core\Orm\Query;

class ModelUtility
{
    /**
     * Gets all models for a given query using cursor pagination.
     * This will only work if the model has an auto increment primary
     * key named "id". The results will be sorted in ascending order
     * by the primary key.
     *
     * @template T
     *
     * @param Query<T> $query
     *
     * @return T[]
     */
    public static function getAllModels(Query $query): array
    {
        // We need preserve_keys=false because otherwise this will
        // only return the last page of results when used with
        // yield from. Source: https://www.php.net/manual/en/language.generators.syntax.php#control-structures.yield.from
        return iterator_to_array(self::getAllModelsGenerator($query), false);
    }

    /**
     * Gets all models for a given query using cursor pagination.
     * This will only work if the model has an auto increment primary
     * key named "id". The results will be sorted in ascending order
     * by the primary key.
     *
     * @template T
     *
     * @param Query<T> $query
     *
     * @return Generator<T>
     */
    public static function getAllModelsGenerator(Query $query): Generator
    {
        $perPage = Query::MAX_LIMIT;
        $hasMore = true;
        $query->sort('id ASC');

        while ($hasMore) {
            $rows = $query->first($perPage);
            yield from $rows;

            $hasMore = count($rows) == $perPage;
            $lastId = count($rows) ? end($rows)->id : null;
            $query = clone $query;
            $query->where('id', $lastId, '>');
        }
    }
}
