<?php

namespace App\Core\Utils;

use App\Core\Utils\InfuseUtility as Utility;
use Twig\Environment;

class DebugContext
{
    private ?string $requestId = null;
    private string $correlationId;

    public function __construct(private string $environment, private ?Environment $twig = null)
    {
        $this->correlationId = strtolower(Utility::guid());
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Gets the current request ID. If we are not in
     * a request context then this will be null.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Genereates a new random request ID.
     */
    public function generateRequestId(): void
    {
        $this->requestId = $this->correlationId = strtolower(Utility::guid());
        if ($this->twig) {
            $this->twig->addGlobal('requestId', $this->requestId);
            $this->twig->addGlobal('correlationId', $this->correlationId);
        }
    }

    public function setRequestId(string $requestId): void
    {
        // Set the correlation ID to match the request ID
        $this->requestId = $this->correlationId = $requestId;
        if ($this->twig) {
            $this->twig->addGlobal('requestId', $this->requestId);
            $this->twig->addGlobal('correlationId', $this->correlationId);
        }
    }

    /**
     * Gets the current correlation ID.
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
        if ($this->twig) {
            $this->twig->addGlobal('correlationId', $this->correlationId);
        }
    }
}
