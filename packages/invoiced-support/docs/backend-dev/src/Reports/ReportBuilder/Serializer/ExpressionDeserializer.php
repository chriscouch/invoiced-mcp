<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\ValueObjects\ConstantExpression;
use App\Reports\ReportBuilder\ValueObjects\ExpressionList;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Responsible for deserializing expressions, which
 * can be a field reference, list of sub-expressions, or a function.
 */
final class ExpressionDeserializer
{
    private const ALLOWED_OPERATORS = ['+', '-', '*', '/', '%', '^'];
    private const TEMPORAL_UNITS = ['second', 'minute', 'hour', 'day', 'week', 'month', 'quarter', 'year'];
    private const ALLOWED_KEYWORDS = ['null'];

    private static OptionsResolver $resolver;

    /**
     * @param array|string $data
     *
     * @throws ReportException
     */
    public static function deserialize(string $object, $data, JoinCollector $joins, Company $company): ExpressionInterface
    {
        if (is_array($data)) {
            // An array value can be a field or list of sub-expressions.
            $keys = array_keys($data);
            // sequential array is a list of expressions
            if (0 == count($keys) || '0' == $keys[0]) {
                $list = [];
                foreach ($data as $element) {
                    $list[] = self::deserialize($object, $element, $joins, $company);
                }

                return new ExpressionList($list);
            }

            // associative array is a wrapped expression of unknown type
            return self::deserializeWrappedExpression($object, $data, $joins, $company);
        }

        // We have a scalar value that we must validate
        // Allowed values are a list of string constants (and, or, +, -, etc), numeric values, or string values
        if (is_numeric($data) || in_array($data, self::ALLOWED_OPERATORS) || in_array($data, self::TEMPORAL_UNITS) || in_array($data, self::ALLOWED_KEYWORDS)) {
            return new ConstantExpression($data, false);
        }

        // Mark any unrecognized strings as unsafe
        return new ConstantExpression($data, true);
    }

    /**
     * Deserializes a field which wraps an expression of unknown type. The expression
     * type can be a list of sub-expressions, function, or field reference.
     *
     * @throws ReportException
     */
    private static function deserializeWrappedExpression(string $object, array $fieldData, JoinCollector $joins, Company $company): ExpressionInterface
    {
        // Validate input
        try {
            $fieldData = self::getResolver()->resolve($fieldData);
        } catch (ExceptionInterface $e) {
            throw new ReportException('Could not validate field: '.$e->getMessage());
        }

        if (count($fieldData['formula']) > 0) {
            return ExpressionDeserializer::deserialize($object, $fieldData['formula'], $joins, $company);
        }

        if ($fieldData['function']) {
            return FunctionDeserializer::deserialize($object, $fieldData['function'], $fieldData['arguments'], $joins, $company);
        }

        if ($fieldData['id']) {
            return FieldReferenceDeserializer::deserialize($object, $fieldData['id'], $joins, $company);
        }

        if ($fieldData['operator']) {
            return FilterConditionDeserializer::deserialize($object, $fieldData['field'], $fieldData['operator'], $fieldData['value'], $joins, $company);
        }

        throw new ReportException('Missing `formula`, `function`, `id`, or `operator` parameter of field');
    }

    private static function getResolver(): OptionsResolver
    {
        if (isset(self::$resolver)) {
            return self::$resolver;
        }

        $resolver = new OptionsResolver();

        $resolver->setDefined(['arguments', 'formula', 'function', 'id', 'field', 'operator', 'value']);
        $resolver->setAllowedTypes('arguments', ['array']);
        $resolver->setDefault('arguments', []);
        $resolver->setAllowedTypes('formula', ['array']);
        $resolver->setDefault('formula', []);
        $resolver->setAllowedTypes('function', ['string', 'null']);
        $resolver->setDefault('function', null);
        $resolver->setAllowedValues('function', FunctionDeserializer::ALLOWED_FUNCTIONS);
        $resolver->setAllowedTypes('id', ['string']);
        $resolver->setDefault('id', '');
        $resolver->setAllowedTypes('field', ['array', 'null']);
        $resolver->setDefault('field', null);
        $resolver->setAllowedTypes('operator', ['string', 'null']);
        $resolver->setDefault('operator', null);
        $resolver->setAllowedValues('operator', array_merge([null], FilterConditionDeserializer::ALLOWED_COMPARISON_OPERATORS));
        $resolver->setDefault('value', null);
        self::$resolver = $resolver;

        return $resolver;
    }
}
