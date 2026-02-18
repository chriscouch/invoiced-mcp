<?php

namespace App\EntryPoint\Controller\Api;

use App\Imports\Api\CreateImportRoute;
use App\Imports\Api\ListImportedObjectsRoute;
use App\Imports\Api\ListImportsRoute;
use App\Imports\Api\RetrieveImportRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ImportApiController extends AbstractApiController
{
    #[Route(path: '/imports', name: 'list_imports', methods: ['GET'])]
    public function listAll(ListImportsRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/imports', name: 'create_import', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function create(CreateImportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/imports/{model_id}', name: 'retrieve_import', methods: ['GET'])]
    public function retrieve(RetrieveImportRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/imports/{model_id}/imported_objects', name: 'list_imported_objects', methods: ['GET'])]
    public function listImportedObjects(ListImportedObjectsRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
