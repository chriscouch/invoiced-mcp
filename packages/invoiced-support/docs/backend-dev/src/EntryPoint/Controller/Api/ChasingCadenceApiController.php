<?php

namespace App\EntryPoint\Controller\Api;

use App\Chasing\Api\AssignCadenceRoute;
use App\Chasing\Api\CreateChasingCadenceRoute;
use App\Chasing\Api\DeleteChasingCadenceRoute;
use App\Chasing\Api\EditChasingCadenceRoute;
use App\Chasing\Api\ListChasingCadencesRoute;
use App\Chasing\Api\RetrieveChasingCadenceRoute;
use App\Chasing\Api\RunCadenceRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ChasingCadenceApiController extends AbstractApiController
{
    #[Route(path: '/chasing_cadences', name: 'list_chasing_cadences', methods: ['GET'])]
    public function listAll(ListChasingCadencesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/chasing_cadences', name: 'create_chasing_cadence', methods: ['POST'])]
    public function create(CreateChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/chasing_cadences/{model_id}', name: 'retrieve_chasing_cadence', methods: ['GET'])]
    public function retrieve(RetrieveChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/chasing_cadences/{model_id}', name: 'edit_chasing_cadence', methods: ['PATCH'])]
    public function edit(EditChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/chasing_cadences/{model_id}', name: 'delete_chasing_cadence', methods: ['DELETE'])]
    public function delete(DeleteChasingCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/chasing_cadences/{model_id}/runs', name: 'run_chasing_cadence', methods: ['POST'], defaults: ['no_database_transaction' => true])]
    public function run(RunCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/chasing_cadences/{model_id}/assign', name: 'assign_chasing_cadence', methods: ['POST'])]
    public function assign(AssignCadenceRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
