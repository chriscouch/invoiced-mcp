<?php

namespace App\Core\RestApi\ValueObjects;

use Symfony\Component\HttpFoundation\Request;

final readonly class ApiCallContext
{
    public function __construct(
        public Request $request,
        public array $queryParameters,
        public array $requestParameters,
        public ApiRouteDefinition $definition,
    ) {
    }

    /**
     * Makes a new instance with different request parameters.
     */
    public function withRequestParameters(array $parameters): self
    {
        return new self($this->request, $this->queryParameters, $parameters, $this->definition);
    }

    /**
     * Extracts a request parameter and returns a new instance without it.
     */
    public function extractRequestParameter(string $key): array
    {
        $parameters = $this->requestParameters;
        $value = $parameters[$key] ?? null;
        unset($parameters[$key]);

        return [$value, $this->withRequestParameters($parameters)];
    }
}
