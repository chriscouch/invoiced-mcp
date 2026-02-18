<?php

namespace App\Core\Orm;

use App\Core\Orm\Relation\Relationship;

class Hydrator
{
    /**
     * @param class-string<Model>|Model $modelClass
     *
     * @return Model[]
     */
    public static function hydrate(array $result, string|Model $modelClass, array $eagerLoaded): array
    {
        $ids = [];
        $eagerLoadedProperties = [];
        foreach ($eagerLoaded as $k) {
            /** @var Property $property */
            $property = $modelClass::definition()->get($k);
            $eagerLoadedProperties[$k] = $property;
            $ids[$k] = [];
        }

        // fetch the models matching the query
        /** @var Model[] $models */
        $models = [];
        foreach ($result as $j => $row) {
            // type-cast the values because they came from the database
            foreach ($row as $k => &$v) {
                if ($property = $modelClass::definition()->get($k)) {
                    $v = Type::cast($property, $v);
                }
            }

            // create the model and cache the loaded values
            /** @var Model $model */
            $model = new $modelClass($row);
            $models[] = $model;

            // capture any local ids for eager loading relationships
            foreach ($eagerLoaded as $k) {
                $localKey = $eagerLoadedProperties[$k]->local_key;
                if (isset($row[$localKey])) {
                    $ids[$k][$j] = $row[$localKey];
                }
            }
        }

        // hydrate the eager loaded relationships
        foreach ($eagerLoaded as $k) {
            $property = $eagerLoadedProperties[$k];
            $relationModelClass = (string) $property->relation;
            $type = $property->relation_type;

            if (Relationship::BELONGS_TO == $type) {
                $relationships = self::fetchRelationships($relationModelClass, $ids[$k], (string) $property->foreign_key);

                foreach ($ids[$k] as $j => $id) {
                    if (isset($relationships[$id])) {
                        if ($property->persisted) {
                            $models[$j]->setRelation($k, $relationships[$id]);
                            // older style properties do not support this type of hydration
                        } else {
                            $models[$j]->hydrateValue($k, $relationships[$id]);
                        }
                    }
                }
            } elseif (Relationship::HAS_ONE == $type) {
                $relationships = self::fetchRelationships($relationModelClass, $ids[$k], (string) $property->foreign_key);

                foreach ($ids[$k] as $j => $id) {
                    if (isset($relationships[$id])) {
                        if ($property->persisted) {
                            $models[$j]->setRelation($k, $relationships[$id]);
                            // older style properties do not support this type of hydration
                        } else {
                            $models[$j]->hydrateValue($k, $relationships[$id]);
                        }
                    } else {
                        // when using has one eager loading we must
                        // explicitly mark the relationship as null
                        // for models not found during eager loading
                        // or else it will trigger another DB call
                        $models[$j]->clearRelation($k);

                        // older style properties do not support this type of hydration
                        if (!$property->persisted) {
                            $models[$j]->hydrateValue($k, null);
                        }
                    }
                }
            } elseif (Relationship::HAS_MANY == $type) {
                $relationships = self::fetchRelationshipsNested($relationModelClass, $ids[$k], (string) $property->foreign_key);

                foreach ($ids[$k] as $j => $id) {
                    if (isset($relationships[$id])) {
                        $models[$j]->setRelationCollection($k, $relationships[$id]);
                        // older style properties do not support this type of hydration
                        if (!$property->persisted) {
                            $models[$j]->hydrateValue($k, $relationships[$id]);
                        }
                    } else {
                        $models[$j]->setRelationCollection($k, []);
                        // older style properties do not support this type of hydration
                        if (!$property->persisted) {
                            $models[$j]->hydrateValue($k, []);
                        }
                    }
                }
            }
        }

        return $models;
    }

    /**
     * Hydrates the eager-loaded relationships for a given set of IDs.
     *
     * @return Model[]
     */
    private static function fetchRelationships(string $modelClass, array $ids, string $foreignKey): array
    {
        $models = self::fetchRelationshipsData($modelClass, $ids, $foreignKey);
        $result = [];
        foreach ($models as $model) {
            $result[$model->$foreignKey] = $model;
        }

        return $result;
    }

    /**
     * Hydrates the eager-loaded relationships for a given set of IDs.
     *
     * @return Model[][]
     */
    private static function fetchRelationshipsNested(string $modelClass, array $ids, string $foreignKey): array
    {
        $models = self::fetchRelationshipsData($modelClass, $ids, $foreignKey);
        $result = [];
        foreach ($models as $model) {
            if (!isset($result[$model->$foreignKey])) {
                $result[$model->$foreignKey] = [];
            }
            $result[$model->$foreignKey][] = $model;
        }

        return $result;
    }

    private static function fetchRelationshipsData(string $modelClass, array $ids, string $foreignKey): array
    {
        $uniqueIds = array_unique($ids);
        if (0 === count($uniqueIds)) {
            return [];
        }

        $in = $foreignKey.' IN ('.implode(',', $uniqueIds).')';

        return $modelClass::where($in)
            ->first(Query::MAX_LIMIT);
    }
}
