<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\Search\Api\SearchRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class SearchApiController extends AbstractApiController
{
    #[Route(path: '/search', name: 'search', methods: ['GET'])]
    public function search(SearchRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
