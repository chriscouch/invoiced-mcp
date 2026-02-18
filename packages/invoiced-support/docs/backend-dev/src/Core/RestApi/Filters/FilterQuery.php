<?php

namespace App\Core\RestApi\Filters;

use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\Orm\Query;
use App\Metadata\Exception\MetadataStorageException;
use App\Metadata\Libs\MetadataQuery;
use App\Metadata\ValueObjects\MetadataQueryCondition;

final class FilterQuery
{
    private const BASIC_OPERATORS = [
        FilterOperator::Equal,
        FilterOperator::NotEqual,
        FilterOperator::GreaterThanOrEqual,
        FilterOperator::GreaterThan,
        FilterOperator::LessThan,
        FilterOperator::LessThanOrEqual,
    ];

    /**
     * Adds a list filter to an ORM query.
     *
     * @throws InvalidRequest
     */
    public static function addToQuery(ListFilter $filter, Query $query): void
    {
        foreach ($filter->filters as $filter) {
            if (str_starts_with($filter->field, 'metadata.')) {
                self::addMetadataFilter($filter, $query);
            } elseif (in_array($filter->operator, self::BASIC_OPERATORS)) {
                $query->where($filter->field, $filter->value, $filter->operator->value);
            } elseif (FilterOperator::Contains == $filter->operator) {
                $query->where($filter->field, '%'.$filter->value.'%', 'LIKE');
            } elseif (FilterOperator::NotContains == $filter->operator) {
                $query->where($filter->field, '%'.$filter->value.'%', 'NOT LIKE');
            } elseif (FilterOperator::StartsWith == $filter->operator) {
                $query->where($filter->field, $filter->value.'%', 'LIKE');
            } elseif (FilterOperator::EndsWith == $filter->operator) {
                $query->where($filter->field, '%'.$filter->value, 'LIKE');
            } elseif (FilterOperator::Empty == $filter->operator) {
                $query->where($filter->field, null, '=');
            } elseif (FilterOperator::NotEmpty == $filter->operator) {
                $query->where($filter->field, null, '<>');
            } else {
                throw new InvalidRequest('Operator not recognized: '.$filter->operator->value);
            }
        }
    }

    /**
     * Adds a filter condition for a metadata field.
     */
    private static function addMetadataFilter(FilterCondition $filter, Query $query): void
    {
        $attributeName = str_replace('metadata.', '', $filter->field);

        $value = $filter->value;
        if (in_array($filter->operator, self::BASIC_OPERATORS)) {
            $operator = $filter->operator->value;
        } elseif (FilterOperator::Contains == $filter->operator) {
            $operator = 'LIKE';
            $value = '%'.$filter->value.'%';
        } elseif (FilterOperator::NotContains == $filter->operator) {
            $operator = 'NOT LIKE';
            $value = '%'.$filter->value.'%';
        } elseif (FilterOperator::StartsWith == $filter->operator) {
            $operator = 'LIKE';
            $value = $filter->value.'%';
        } elseif (FilterOperator::EndsWith == $filter->operator) {
            $operator = 'LIKE';
            $value = '%'.$filter->value;
        } elseif (FilterOperator::Empty == $filter->operator) {
            $operator = '=';
            $value = null;
        } elseif (FilterOperator::NotEmpty == $filter->operator) {
            $operator = '<>';
            $value = null;
        } else {
            throw new InvalidRequest('Operator not recognized: '.$filter->operator->value);
        }

        try {
            $condition = new MetadataQueryCondition($attributeName, $value, $operator);
            MetadataQuery::addTo($query, [$condition]);
        } catch (MetadataStorageException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
