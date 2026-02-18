<?php

namespace App\Entity\Forms;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

class IntegrationLogSearch
{
    #[Assert\NotBlank]
    private string $environment;
    #[Assert\NotBlank]
    private int $tenant;
    #[Assert\NotBlank]
    private DateTimeInterface $startTime;
    #[Assert\NotBlank]
    private DateTimeInterface $endTime;
    private int $numResults = 100;
    private ?string $searchTerm = null;
    private string $channel = '';
    private ?string $minLevel = null;
    private ?string $correlationId = null;

    public function __construct()
    {
        $this->startTime = CarbonImmutable::now()->subDays(1);
        $this->endTime = CarbonImmutable::now();
    }

    public function getId(): string
    {
        return $this->environment.':'.$this->tenant;
    }

    public function toString(): string
    {
        $parts = [
            'Tenant' => $this->tenant,
            'Time Range' => $this->startTime->format('n/j/Y g:i:s a').' - '.$this->endTime->format('n/j/Y g:i:s a'),
        ];

        if ($this->searchTerm) {
            $parts['Search Term'] = $this->searchTerm;
        }

        if ($this->channel) {
            $parts['Channel'] = $this->channel;
        }

        if ($this->minLevel) {
            $parts['Minimum Level'] = $this->minLevel;
        }

        if ($this->correlationId) {
            $parts['Correlation ID'] = $this->correlationId;
        }

        return join(', ', array_map(function ($v, $k) {
            return "$k: $v";
        }, $parts, array_keys($parts)));
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): self
    {
        $this->environment = $environment;

        return $this;
    }

    public function getTenant(): int
    {
        return $this->tenant;
    }

    public function setTenant(int $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getNumResults(): int
    {
        return $this->numResults;
    }

    public function setNumResults(int $numResults): void
    {
        $this->numResults = $numResults;
    }

    public function getStartTime(): DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(DateTimeInterface $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(DateTimeInterface $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(?string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }

    public function getMinLevel(): ?string
    {
        return $this->minLevel;
    }

    public function setMinLevel(?string $minLevel): void
    {
        $this->minLevel = $minLevel;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }
}
