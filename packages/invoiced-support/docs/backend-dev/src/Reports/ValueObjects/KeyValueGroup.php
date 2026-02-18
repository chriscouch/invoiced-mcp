<?php

namespace App\Reports\ValueObjects;

final class KeyValueGroup extends AbstractGroup
{
    private array $lines = [];

    public function getType(): string
    {
        return 'keyvalue';
    }

    /**
     * Adds a line to the group.
     *
     * @param array|string $value
     *
     * @return $this
     */
    public function addLine(string $name, $value)
    {
        $this->lines[] = [
            'name' => $name,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Adds multiple lines to this gruop.
     */
    public function addLines(array $lines): void
    {
        $this->lines = array_merge($this->lines, $lines);
    }

    /**
     * Gets the lines for this group.
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * Gets the value for a given line.
     */
    public function getValue(string $k): ?string
    {
        foreach ($this->lines as $line) {
            if ($line['name'] == $k) {
                return $line['value'];
            }
        }

        return null;
    }
}
