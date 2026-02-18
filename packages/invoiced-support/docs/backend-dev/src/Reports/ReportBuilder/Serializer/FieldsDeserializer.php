<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Enums\ColumnType;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Interfaces\ExpressionInterface;
use App\Reports\ReportBuilder\ValueObjects\FieldReferenceExpression;
use App\Reports\ReportBuilder\ValueObjects\Fields;
use App\Reports\ReportBuilder\ValueObjects\FunctionExpression;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use App\Reports\ReportBuilder\ValueObjects\SelectColumn;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Responsible for deserializing the fields which are selected in
 * the data query and presented as columns in the report.
 */
final class FieldsDeserializer
{
    private static OptionsResolver $resolver;

    /**
     * @throws ReportException
     */
    public static function deserialize(string $object, array $columns, JoinCollector $joins, Company $company): Fields
    {
        $fields = [];
        foreach ($columns as $columnData) {
            if (!is_array($columnData)) {
                throw new ReportException('Unrecognized select column');
            }

            // Validate input
            try {
                $columnData = self::getResolver()->resolve($columnData);
            } catch (ExceptionInterface $e) {
                throw new ReportException('Could not validate field: '.$e->getMessage());
            }

            $expression = ExpressionDeserializer::deserialize($object, $columnData['field'], $joins, $company);
            $summarize = 'sum' == $columnData['subtotal'];
            if ($expression instanceof FieldReferenceExpression || $expression instanceof FunctionExpression) {
                $summarize = $expression->shouldSummarize();
            }
            $hideEmptyValues = $columnData['hide_empty'];

            $fields[] = new SelectColumn(
                expression: $expression,
                name: $columnData['name'] ?? $expression->getName() ?? '',
                type: self::getType($columnData, $expression),
                unit: $columnData['unit'],
                shouldSummarize: $summarize,
                hideEmptyValues: $hideEmptyValues
            );
        }

        return new Fields($fields);
    }

    private static function getType(array $columnData, ExpressionInterface $expression): ColumnType
    {
        if (isset($columnData['type']) && $type = ColumnType::tryFrom($columnData['type'])) {
            return $type;
        }

        if ($type = $expression->getType()) {
            return $type;
        }

        return ColumnType::String;
    }

    private static function getResolver(): OptionsResolver
    {
        if (isset(self::$resolver)) {
            return self::$resolver;
        }

        $resolver = new OptionsResolver();

        $resolver->setRequired('field');
        $resolver->setAllowedTypes('field', ['array']);

        $resolver->setDefined(['hide_empty', 'name', 'subtotal', 'type', 'unit']);
        $resolver->setAllowedTypes('hide_empty', ['boolean', 'null']);
        $resolver->setDefault('hide_empty', false);
        $resolver->setAllowedTypes('name', ['string', 'null']);
        $resolver->setDefault('name', null);
        $resolver->setAllowedValues('subtotal', ['none', 'sum']);
        $resolver->setDefault('subtotal', 'none');
        $resolver->setAllowedValues('type', [null, 'boolean', 'date', 'datetime', 'integer', 'float', 'money', 'string']);
        $resolver->setDefault('type', null);
        $resolver->setAllowedTypes('unit', ['string', 'null']);
        $resolver->setDefault('unit', null);
        self::$resolver = $resolver;

        return $resolver;
    }
}
