<?php

namespace App\Core\RestApi\Interfaces;

use App\Core\RestApi\Exception\ApiHttpException;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

/**
 * All API endpoints must implement this interface.
 */
interface ApiRouteInterface
{
    /**
     * Gets the definition of this API route to describe behavior.
     */
    public function getDefinition(): ApiRouteDefinition;

    /**
     * Performs the API route and generates a result. The return
     * value can be a response object or a value which can be
     * serialized.
     *
     * @throws ApiHttpException when an API error occurs
     */
    public function buildResponse(ApiCallContext $context): mixed;

    /**
     * Generates a fresh response object for a successful result
     * that is passed to the serializer. This will be called after
     * buildResponse() if it does not return a Response object.
     */
    public function getSuccessfulResponse(): Response;
}
