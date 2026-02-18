<?php

namespace App\EntryPoint\Controller\Api;

use App\Tokenization\Api\RefreshPublishableKeysApiRoute;
use App\Tokenization\Api\ListPublishableKeysApiRoute;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route(host: '%app.api_domain%', name: 'api_')]
class PublishableKeyApiController extends AbstractApiController
{

    #[Route(path: '/publishable_keys', name: 'list_publishable_keys', methods: ['GET'])]
    public function listAll(ListPublishableKeysApiRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/publishable_keys/{id}', name: 'refresh_publishable_keys', methods: ['POST'])]
    public function refresh(RefreshPublishableKeysApiRoute $route): Response
    {
        return $this->runRoute($route);
    }
}