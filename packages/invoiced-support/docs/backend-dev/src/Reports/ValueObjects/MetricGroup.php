<?php

namespace App\Reports\ValueObjects;

final class MetricGroup extends AbstractGroup
{
    private array $metrics = [];

    public function getType(): string
    {
        return 'metric';
    }

    /**
     * Adds a metric to the group.
     *
     * @param array|string $value
     */
    public function addMetric(string $name, $value): void
    {
        $this->metrics[] = [
            'name' => $name,
            'value' => $value,
        ];
    }

    /**
     * Adds multiple metrics to the group.
     */
    public function addMetrics(array $metrics): void
    {
        $this->metrics = array_merge($this->metrics, $metrics);
    }

    /**
     * Gets the lines for this group.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Gets the value for a given line.
     */
    public function getValue(string $k): ?string
    {
        foreach ($this->metrics as $metric) {
            if ($metric['name'] == $k) {
                return $metric['value'];
            }
        }

        return null;
    }
}
