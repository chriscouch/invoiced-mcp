<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\FilterCondition;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterConditionDeserializer
{
    public const ALLOWED_COMPARISON_OPERATORS = ['and', 'or', '=', '>=', '>', '<=', '<', '<>', '!=', 'between', 'not_between', 'contains', 'not_contains'];

    private static OptionsResolver $resolver;

    /**
     * @throws ReportException
     */
    public static function deserializeWithResolve(string $object, array $conditionData, JoinCollector $joins, Company $company): FilterCondition
    {
        // Validate input
        try {
            $conditionData = self::getResolver()->resolve($conditionData);
        } catch (ExceptionInterface $e) {
            throw new ReportException('Could not validate filter condition: '.$e->getMessage());
        }

        return self::deserialize($object, $conditionData['field'], $conditionData['operator'], $conditionData['value'], $joins, $company);
    }

    /**
     * @throws ReportException
     */
    public static function deserialize(string $object, ?array $field, string $operator, mixed $value, JoinCollector $joins, Company $company): FilterCondition
    {
        // Handle AND / OR
        if (in_array($operator, ['and', 'or'])) {
            $conditions = [];
            foreach ($value as $subConditionData) {
                $conditions[] = self::deserializeWithResolve($object, $subConditionData, $joins, $company);
            }

            if (0 == count($conditions)) {
                throw new ReportException("Missing conditions list on $operator statement");
            }

            return new FilterCondition(
                null,
                $operator,
                $conditions
            );
        }

        if (!$field) {
            throw new ReportException('Missing `field` on filter condition');
        }

        // Treat as an expression if the value is in the format {"field": ...}
        if (is_array($value) && isset($value['field'])) {
            $value = ExpressionDeserializer::deserialize($object, $value['field'], $joins, $company);
        }

        return new FilterCondition(
            field: ExpressionDeserializer::deserialize($object, $field, $joins, $company),
            operator: $operator,
            value: $value
        );
    }

    private static function getResolver(): OptionsResolver
    {
        if (isset(self::$resolver)) {
            return self::$resolver;
        }

        $resolver = new OptionsResolver();

        $resolver->setDefined(['field', 'operator', 'value']);
        $resolver->setAllowedTypes('field', ['array', 'null']);
        $resolver->setDefault('field', null);
        $resolver->setAllowedValues('operator', self::ALLOWED_COMPARISON_OPERATORS);
        $resolver->setDefault('operator', '=');
        $resolver->setAllowedTypes('value', ['array', 'boolean', 'null', 'numeric', 'string']);
        $resolver->setDefault('value', null);
        self::$resolver = $resolver;

        return $resolver;
    }
}
