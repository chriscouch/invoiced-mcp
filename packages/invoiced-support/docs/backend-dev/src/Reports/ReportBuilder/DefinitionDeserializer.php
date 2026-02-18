<?php

namespace App\Reports\ReportBuilder;

use App\Companies\Models\Company;
use App\Companies\Models\Member;
use App\Reports\Exceptions\ReportException;
use App\Reports\ReportBuilder\Serializer\SectionDeserializer;
use App\Reports\ReportBuilder\ValueObjects\Definition;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Deserializes a serialized report definition into a definition object.
 */
final class DefinitionDeserializer
{
    private const MAX_SECTIONS = 10;

    /**
     * Given a JSON input it deserializes into a report definition.
     *
     * @throws ReportException
     */
    public static function deserialize(string $input, Company $company, ?Member $member): Definition
    {
        $json = json_decode($input, true);
        if (!is_array($json)) {
            throw new ReportException(json_last_error_msg());
        }

        try {
            $data = self::getResolver()->resolve($json);
        } catch (ExceptionInterface $e) {
            throw new ReportException($e->getMessage());
        }

        $sections = [];
        foreach ($data['sections'] as $sectionData) {
            if (!is_array($sectionData)) {
                throw new ReportException('Invalid section');
            }

            $sections[] = SectionDeserializer::deserialize($sectionData, $company, $member);
        }

        // check limits
        if (count($sections) < 1 || count($sections) > self::MAX_SECTIONS) {
            throw new ReportException('Reports must have at least one section and no more than '.self::MAX_SECTIONS.' sections.');
        }

        return new Definition($company, $data['title'], $sections, $input);
    }

    private static function getResolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setRequired('sections');
        $resolver->setAllowedTypes('sections', ['array']);
        $resolver->setDefined(['title']);
        $resolver->setDefault('title', '');
        $resolver->setAllowedTypes('title', ['string']);
        $resolver->setDefined(['version']);
        $resolver->setAllowedTypes('version', ['integer']);
        $resolver->setDefault('version', 1);

        return $resolver;
    }
}
