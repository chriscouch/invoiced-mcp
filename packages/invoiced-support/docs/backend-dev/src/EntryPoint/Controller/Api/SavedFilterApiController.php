<?php

namespace App\EntryPoint\Controller\Api;

use App\Core\RestApi\SavedFilters\Api\CreateFilterRoute;
use App\Core\RestApi\SavedFilters\Api\DeleteFilterRoute;
use App\Core\RestApi\SavedFilters\Api\EditFilterRoute;
use App\Core\RestApi\SavedFilters\Api\ListFiltersRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class SavedFilterApiController extends AbstractApiController
{
    #[Route(path: '/ui/filters', name: 'list_ui_filters', methods: ['GET'])]
    public function listAll(ListFiltersRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/ui/filters', name: 'create_ui_filter', methods: ['POST'])]
    public function create(CreateFilterRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/ui/filters/{model_id}', name: 'edit_ui_filter', methods: ['PATCH'])]
    public function edit(EditFilterRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/ui/filters/{model_id}', name: 'delete_ui_filter', methods: ['DELETE'])]
    public function delete(DeleteFilterRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
