<?php

namespace App\Entity\Forms;

use DateTime;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

class ApiLogSearch
{
    #[Assert\NotBlank]
    private string $environment;

    #[Assert\NotBlank]
    private ?int $tenant = null;
    private ?string $requestId = null;
    private ?string $correlationId = null;
    private ?string $method = null;
    private ?string $endpoint = null;
    private ?string $route_name = null;
    private ?int $statusCode = null;
    private ?string $userAgent = null;
    private ?DateTime $startTime = null;
    private ?DateTime $endTime = null;
    private int $numResults = 25;

    public function getId(): string
    {
        return $this->environment.':'.$this->tenant;
    }

    public function toString(): string
    {
        $parts = [
            'Tenant' => $this->tenant,
        ];

        if ($this->requestId) {
            $parts['Request ID'] = $this->requestId;
        }

        if ($this->correlationId) {
            $parts['Correlation ID'] = $this->correlationId;
        }

        if ($this->method) {
            $parts['Method'] = $this->method;
        }

        if ($this->endpoint) {
            $parts['Endpoint'] = $this->endpoint;
        }

        if ($this->statusCode) {
            $parts['Status Code'] = $this->statusCode;
        }

        if ($this->userAgent) {
            $parts['User Agent'] = $this->userAgent;
        }

        return join(', ', array_map(function ($v, $k) {
            return "$k: $v";
        }, $parts, array_keys($parts)));
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): ApiLogSearch
    {
        $this->environment = $environment;

        return $this;
    }

    public function getTenant(): ?int
    {
        return $this->tenant;
    }

    public function setTenant(int $tenant): ApiLogSearch
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): ApiLogSearch
    {
        $this->requestId = $requestId;

        return $this;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(?string $correlationId): ApiLogSearch
    {
        $this->correlationId = $correlationId;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): ApiLogSearch
    {
        $this->method = $method;

        return $this;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): ApiLogSearch
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->route_name;
    }

    public function setRouteName(?string $route_name): void
    {
        $this->route_name = $route_name;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): ApiLogSearch
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): ApiLogSearch
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getNumResults(): int
    {
        return $this->numResults;
    }

    public function setNumResults(int $numResults): void
    {
        $this->numResults = $numResults;
    }

    public function getStartTime(): ?DateTime
    {
        return $this->startTime;
    }

    public function getStartTimeUtc(): ?DateTime
    {
        return $this->startTime ? $this->startTime->setTimezone(new DateTimeZone('UTC')) : null;
    }

    public function setStartTime(?DateTime $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): ?DateTime
    {
        return $this->endTime;
    }

    public function getEndTimeUtc(): ?DateTime
    {
        return $this->endTime ? $this->endTime->setTimezone(new DateTimeZone('UTC')) : null;
    }

    public function setEndTime(?DateTime $endTime): void
    {
        $this->endTime = $endTime;
    }
}
