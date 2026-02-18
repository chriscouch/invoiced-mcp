<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Interfaces\ApiRouteInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiRoute implements ApiRouteInterface
{
    public function getSuccessfulResponse(): Response
    {
        return new Response();
    }

    /**
     * Builds a request not recognized error.
     */
    protected function requestNotRecognizedError(Request $request): InvalidRequest
    {
        return new InvalidRequest('Request was not recognized: '.$request->getMethod().' '.$request->getPathInfo(), 404);
    }
}
