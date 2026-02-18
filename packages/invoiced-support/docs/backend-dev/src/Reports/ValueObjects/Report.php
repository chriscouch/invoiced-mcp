<?php

namespace App\Reports\ValueObjects;

use App\Companies\Models\Company;
use App\Reports\ReportBuilder\ValueObjects\Definition;
use Carbon\CarbonImmutable;
use ICanBoogie\Inflector;

final class Report
{
    private string $title = '';
    private string $filename = '';
    private ?string $definition = null;
    private ?array $parameters = null;
    /** @var Section[] */
    private array $sections = [];
    private CarbonImmutable $time;

    public function __construct(private Company $company)
    {
        $this->time = CarbonImmutable::now();
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setTime(CarbonImmutable $time): void
    {
        $this->time = $time;
    }

    public function getTime(): CarbonImmutable
    {
        return $this->time;
    }

    /**
     * Sets the report title.
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * Gets the title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Sets the report filename.
     */
    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    /**
     * Gets the title.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getDefinition(): ?string
    {
        return $this->definition;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function setDefinition(Definition $definition): void
    {
        $this->definition = (string) $definition;
    }

    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * Adds a section.
     *
     * @return $this
     */
    public function addSection(Section $section)
    {
        $this->sections[] = $section;

        return $this;
    }

    /**
     * Adds a section at a specific offset.
     *
     * @return $this
     */
    public function addSectionAtOffset(Section $section, int $offset)
    {
        array_splice($this->sections, $offset, 0, [$section]);

        return $this;
    }

    /**
     * Gets the sections.
     *
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    public function getNamedParameters(): array
    {
        $result = [];
        if (!$this->parameters) {
            return $result;
        }

        foreach ($this->parameters as $name => $value) {
            if ('$dateRange' == $name && is_array($value)) {
                $value = $value['start'].' - '.$value['end'];
            } elseif ('$currency' == $name) {
                $value = strtoupper($value);
            } elseif ('$startMonth' == $name || '$endMonth' == $name) {
                continue;
            }

            if (is_array($value)) {
                $values = [];
                foreach ($value as $subName => $subValue) {
                    $subName = Inflector::get()->titleize($subName);
                    $values[] = $subName.': '.$subValue;
                }
                $value = join(', ', $values);
            }

            $result[] = [
                'name' => $this->parameterName($name),
                'value' => $value,
            ];
        }

        return $result;
    }

    private function parameterName(string $name): string
    {
        $name = substr($name, 1); // remove $
        $name = (string) preg_replace('/([a-z])([A-Z])/', '$1 $2', $name); // convert camel case to title case

        return ucfirst($name); // capitalize first letter
    }
}
