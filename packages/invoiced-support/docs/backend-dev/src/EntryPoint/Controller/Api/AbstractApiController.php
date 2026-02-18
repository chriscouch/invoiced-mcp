<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Interfaces\ApiRouteInterface;
use App\Core\RestApi\Libs\ApiRouteRunner;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractApiController
{
    public function __construct(
        private RequestStack $requestStack,
        private ApiRouteRunner $apiRunner,
    ) {
    }

    protected function runRoute(ApiRouteInterface $route): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new ApiError('Missing request');
        }

        return $this->apiRunner->run($route, $request);
    }
}
