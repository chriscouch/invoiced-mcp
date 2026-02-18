<?php

namespace App\Reports\ReportBuilder\Serializer;

use App\Companies\Models\Company;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\ValueObjects\Group;
use App\Reports\ReportBuilder\ValueObjects\GroupField;
use App\Reports\ReportBuilder\ValueObjects\JoinCollector;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Responsible for deserializing the group by parameters.
 */
final class GroupDeserializer
{
    private static OptionsResolver $resolver;

    /**
     * @throws ReportException
     */
    public static function deserialize(string $object, array $groups, JoinCollector $joins, Company $company): Group
    {
        $groupResolver = self::getResolver();
        $fields = [];
        foreach ($groups as $groupData) {
            // Validate input
            if (!is_array($groupData)) {
                throw new ReportException('Unrecognized grouping field');
            }

            try {
                $groupData = $groupResolver->resolve($groupData);
            } catch (ExceptionInterface $e) {
                throw new ReportException('Could not validate group condition: '.$e->getMessage());
            }

            $expression = ExpressionDeserializer::deserialize($object, $groupData['field'], $joins, $company);

            $fields[] = new GroupField(
                expression: $expression,
                ascending: $groupData['ascending'],
                expanded: $groupData['expanded'],
                name: $groupData['name'] ?? $expression->getName() ?? '',
                fillMissingData: $groupData['fill_missing_data'],
            );
        }

        return new Group($fields);
    }

    private static function getResolver(): OptionsResolver
    {
        if (isset(self::$resolver)) {
            return self::$resolver;
        }

        $resolver = new OptionsResolver();

        $resolver->setRequired('field');
        $resolver->setAllowedTypes('field', ['array']);
        $resolver->setDefined(['ascending', 'expanded', 'name', 'fill_missing_data']);
        $resolver->setAllowedTypes('ascending', ['boolean']);
        $resolver->setDefault('ascending', true);
        $resolver->setAllowedTypes('expanded', ['boolean']);
        $resolver->setDefault('expanded', false);
        $resolver->setAllowedTypes('name', ['string', 'null']);
        $resolver->setDefault('name', null);
        $resolver->setAllowedTypes('fill_missing_data', ['boolean']);
        $resolver->setDefault('fill_missing_data', false);
        self::$resolver = $resolver;

        return $resolver;
    }
}
