<?php

namespace App\Integrations\Exceptions;

use Exception;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IntegrationApiException extends Exception
{
    private ?ResponseInterface $response = null;

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function setResponse(?ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
