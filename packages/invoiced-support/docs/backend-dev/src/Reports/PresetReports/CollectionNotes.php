<?php

namespace App\Reports\PresetReports;

class CollectionNotes extends AbstractReportBuilderReport
{
    public static function getId(): string
    {
        return 'collection_notes';
    }

    protected function getDefinition(array $parameters): array
    {
        return $this->getJsonDefinition('collection_notes.json');
    }
}
