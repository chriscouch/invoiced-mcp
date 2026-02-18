<?php

namespace App\Core\Utils;

use DateTimeInterface;
use App\Core\Orm\Definition;
use App\Core\Orm\Model;
use App\Core\Orm\Type;

/**
 * Normalizes ORM objects to an array representation.
 */
final class ModelNormalizer
{
    /**
     * Serializes a model to an array.
     */
    public static function toArray(Model $model, array $exclude = [], array $include = [], array $expand = []): array
    {
        // start with the base representation of the model
        if (method_exists($model, 'withoutArrayHook')) {
            $model->withoutArrayHook();
        }
        $result = $model->toArray();

        // rename relationship properties to a more friendly name, i.e. payment_id -> payment
        foreach ($model::definition()->all() as $k => $property) {
            $localKey = $property->local_key;
            if (!array_key_exists($k, $result) && array_key_exists($localKey, $result) && $localKey != $k && !$property->persisted && in_array($property->relation_type, ['belongs_to', 'has_one'])) {
                $result[$k] = $result[$localKey];
                // TODO: We want to remove the original property name (i.e. payment_id)
                // This is not done yet for BC purposes until all usages
                // in the dashboard have been updated.
//                unset($result[$localKey]);
            }
        }

        // apply namespacing to excluded properties
        $namedExc = [];
        foreach ($exclude as $k) {
            array_set($namedExc, $k, true);
        }

        // apply namespacing to included properties
        $namedInc = [];
        foreach ($include as $k) {
            array_set($namedInc, $k, true);
        }

        // apply namespacing to expanded properties
        $namedExp = [];
        foreach ($expand as $k) {
            array_set($namedExp, $k, true);
        }

        // remove excluded properties
        foreach (array_keys($result) as $k) {
            if (isset($namedExc[$k]) && !is_array($namedExc[$k])) {
                unset($result[$k]);
            }
        }

        // add included properties
        foreach (array_keys($namedInc) as $k) {
            if (!isset($result[$k]) && isset($namedInc[$k])) {
                $result[$k] = $model->$k;
                if ($result[$k] instanceof Model) {
                    $subExc = array_value($namedExc, (string) $k);
                    $subInc = array_value($namedInc, (string) $k);
                    $subExp = array_value($namedExp, (string) $k);

                    // convert exclude, include, and expand into dot notation
                    // then take the keys for a flattened dot notation
                    $flatExc = is_array($subExc) ? array_keys(array_dot($subExc)) : [];
                    $flatInc = is_array($subInc) ? array_keys(array_dot($subInc)) : [];
                    $flatExp = is_array($subExp) ? array_keys(array_dot($subExp)) : [];

                    $result[$k] = self::toArray(
                        model: $result[$k],
                        exclude: $flatExc,
                        include: $flatInc,
                        expand: $flatExp
                    );
                }
            }
        }

        // expand any relational model properties
        $result = self::expand($model, $result, $namedExc, $namedInc, $namedExp);

        // convert date values into ISO 8601
        foreach ($result as $k => &$value) {
            if ($value instanceof DateTimeInterface) {
                $property = $model::definition()->get($k);
                if (Type::DATE == $property?->type) {
                    $value = $value->format('Y-m-d');
                } else {
                    $value = $value->format(DateTimeInterface::ATOM);
                }
            }
        }

        // apply hooks, if available
        if (method_exists($model, 'toArrayHook')) {
            $model->toArrayHook($result, $namedExc, $namedInc, $namedExp);
        }

        // order the properties array by name for consistency
        // since it is constructed in a random order
        ksort($result);

        return $result;
    }

    /**
     * Expands any relational properties within a result.
     */
    private static function expand(Model $model, array $result, array $namedExc, array $namedInc, array $namedExp): array
    {
        $definition = $model::definition();
        foreach ($namedExp as $k => $subExp) {
            $value = array_value($result, $k);
            if (!self::isExpandable($definition, $k, $value)) {
                continue;
            }

            $subExc = array_value($namedExc, $k);
            $subInc = array_value($namedInc, $k);

            // convert exclude, include, and expand into dot notation
            // then take the keys for a flattened dot notation
            $flatExc = is_array($subExc) ? array_keys(array_dot($subExc)) : [];
            $flatInc = is_array($subInc) ? array_keys(array_dot($subInc)) : [];
            $flatExp = is_array($subExp) ? array_keys(array_dot($subExp)) : [];

            // New Pulsar relationships allow for access to a related model
            // instance directly from the property.
            // Legacy relationships store the id. The model instance on
            // legacy relationships needs to be retrieved via 'relation()'.
            if ($definition[$k]->persisted) {
                $relation = $model->relation($k);
            } else {
                $relation = $model->$k;
            }

            if ($relation instanceof Model) {
                $result[$k] = self::toArray(
                    model: $relation,
                    exclude: $flatExc,
                    include: $flatInc,
                    expand: $flatExp
                );
            } else {
                $result[$k] = $relation;
            }
        }

        return $result;
    }

    /**
     * Checks if a model can be expanded.
     */
    private static function isExpandable(Definition $definition, string $k, mixed $value): bool
    {
        // if the value is falsey then do not expand it
        // could be null, excluded, or not included
        if (!$value) {
            return false;
        }

        // if not a property or no relationship model specified
        // then do not expand
        $property = $definition->get($k);

        return $property && $property->relation_type;
    }
}
