<?php

namespace App\Reports\ValueObjects;

final class Section
{
    private array $groups = [];

    public function __construct(private string $title, private string $class = '')
    {
    }

    /**
     * Gets the title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Gets the class.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Adds a group to the section.
     *
     * @return $this
     */
    public function addGroup(AbstractGroup $group)
    {
        $this->groups[] = $group;

        return $this;
    }

    /**
     * Gets the groups.
     *
     * @return AbstractGroup[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }
}
