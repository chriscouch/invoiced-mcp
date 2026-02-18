<?php

namespace App\Reports\ValueObjects;

final class ChartGroup extends AbstractGroup
{
    private string $chartType;
    private array $data;
    private array $chartOptions = [];

    public function getType(): string
    {
        return 'chart';
    }

    public function getChartType(): string
    {
        return $this->chartType;
    }

    public function setChartType(string $chartType): void
    {
        $this->chartType = $chartType;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getChartOptions(): array
    {
        return $this->chartOptions;
    }

    public function setChartOptions(array $chartOptions): void
    {
        $this->chartOptions = $chartOptions;
    }
}
