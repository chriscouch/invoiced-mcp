<?php

namespace App\EntryPoint\Controller\Api;

use App\Exports\Api\CreateExportRoute;
use App\Exports\Api\DeleteExportRoute;
use App\Exports\Api\ListExportsRoute;
use App\Exports\Api\RetrieveExportRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ExportApiController extends AbstractApiController
{
    #[Route(path: '/exports', name: 'list_exports', methods: ['GET'])]
    public function listAll(ListExportsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/exports', name: 'create_export', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function create(CreateExportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/exports/{model_id}', name: 'retrieve_export', methods: ['GET'])]
    public function retrieve(RetrieveExportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/exports/{model_id}', name: 'delete_export', methods: ['DELETE'])]
    public function delete(DeleteExportRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
