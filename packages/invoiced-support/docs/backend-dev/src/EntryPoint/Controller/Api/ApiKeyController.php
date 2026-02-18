<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\RestApi\Api\CreateApiKeyRoute;
use App\Core\RestApi\Api\DeleteApiKeyRoute;
use App\Core\RestApi\Api\ListApiKeysRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ApiKeyController extends AbstractApiController
{
    #[Route(path: '/api_keys', name: 'list_api_keys', methods: ['GET'])]
    public function listAll(ListApiKeysRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/api_keys', name: 'create_api_key', methods: ['POST'])]
    public function create(CreateApiKeyRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/api_keys/{model_id}', name: 'delete_api_key', methods: ['DELETE'])]
    public function delete(DeleteApiKeyRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
