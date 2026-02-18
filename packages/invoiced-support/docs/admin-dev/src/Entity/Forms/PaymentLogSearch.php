<?php

namespace App\Entity\Forms;

use DateTime;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentLogSearch
{
    const DATE_FORMAT = 'Y-m-d H:i:s.v';

    #[Assert\NotBlank]
    private string $applicationId;
    private ?string $tenant = null;
    private ?string $requestId = null;
    private ?string $correlationId = null;
    private ?string $method = null;
    private ?string $endpoint = null;
    private ?string $gateway = null;
    private ?int $statusCode = null;
    private ?string $userAgent = null;
    private ?DateTime $startTime = null;
    private ?DateTime $endTime = null;
    private int $numResults = 25;

    public function getApplicationId(): string
    {
        return $this->applicationId;
    }

    public function toString(): string
    {
        $parts = [];

        if ($this->tenant) {
            $parts['Tenant'] = $this->tenant;
        }

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

        if ($this->gateway) {
            $parts['Gateway'] = $this->gateway;
        }

        if ($this->statusCode) {
            $parts['Status Code'] = $this->statusCode;
        }

        if ($this->userAgent) {
            $parts['User Agent'] = $this->userAgent;
        }

        if ($this->startTime) {
            $parts['Start Time'] = $this->startTime->format(self::DATE_FORMAT);
        }

        if ($this->endTime) {
            $parts['End Time'] = $this->endTime->format(self::DATE_FORMAT);
        }

        return join(', ', array_map(function ($v, $k) {
            return "$k: $v";
        }, $parts, array_keys($parts)));
    }

    public function setApplicationId(string $applicationId): void
    {
        $this->applicationId = $applicationId;
    }

    public function getTenant(): ?string
    {
        return $this->tenant;
    }

    public function setTenant(string $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    public function getGateway(): ?string
    {
        return $this->gateway;
    }

    public function setGateway(?string $method): void
    {
        $this->gateway = $method;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(?string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(?int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
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
