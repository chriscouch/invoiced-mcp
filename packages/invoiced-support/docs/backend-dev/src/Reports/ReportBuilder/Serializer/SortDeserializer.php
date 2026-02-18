<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use App\Reports\ReportBuilder\ValueObjects\Sort;
use App\Reports\ReportBuilder\ValueObjects\SortField;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Responsible for deserializing sort conditions.
 */
final class SortDeserializer
{
    private static OptionsResolver $resolver;

    /**
     * @throws ReportException
     */
    public static function deserialize(string $object, array $sorts, JoinCollector $joins, Company $company): Sort
    {
        $sortResolver = self::getResolver();
        $fields = [];
        foreach ($sorts as $sortData) {
            // Validate input
            if (!is_array($sortData)) {
                throw new ReportException('Unrecognized sort condition');
            }

            try {
                $sortData = $sortResolver->resolve($sortData);
            } catch (ExceptionInterface $e) {
                throw new ReportException('Could not validate sort condition: '.$e->getMessage());
            }

            $fields[] = new SortField(
                expression: ExpressionDeserializer::deserialize($object, $sortData['field'], $joins, $company),
                ascending: $sortData['ascending']
            );
        }

        return new Sort($fields);
    }

    private static function getResolver(): OptionsResolver
    {
        if (isset(self::$resolver)) {
            return self::$resolver;
        }

        $resolver = new OptionsResolver();

        $resolver->setRequired('field');
        $resolver->setAllowedTypes('field', ['array']);
        $resolver->setDefined(['ascending']);
        $resolver->setAllowedTypes('ascending', ['boolean']);
        $resolver->setDefault('ascending', true);
        self::$resolver = $resolver;

        return $resolver;
    }
}
