<?php

namespace App\Reports\ReportBuilder\ValueObjects;

use Countable;

final class Group implements Countable
{
    /**
     * @param GroupField[] $fields
     */
    public function __construct(public readonly array $fields)
    {
    }

    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * @return GroupField[]
     */
    public function getExpandedFields(): array
    {
        $fields = [];
        foreach ($this->fields as $groupField) {
            if ($groupField->expanded) {
                $fields[] = $groupField;
            }
        }

        return $fields;
    }

    /**
     * @return GroupField[]
     */
    public function getCollapsedFields(): array
    {
        $fields = [];
        foreach ($this->fields as $groupField) {
            if (!$groupField->expanded) {
                $fields[] = $groupField;
            }
        }

        return $fields;
    }
}
