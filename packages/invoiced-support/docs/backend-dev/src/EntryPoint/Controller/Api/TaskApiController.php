<?php

namespace App\EntryPoint\Controller\Api;

use App\Chasing\Api\CreateTaskRoute;
use App\Chasing\Api\DeleteTaskRoute;
use App\Chasing\Api\EditTaskRoute;
use App\Chasing\Api\ListTasksRoute;
use App\Chasing\Api\RetrieveTaskRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class TaskApiController extends AbstractApiController
{
    #[Route(path: '/tasks', name: 'list_tasks', methods: ['GET'])]
    public function listAll(ListTasksRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tasks', name: 'create_task', methods: ['POST'])]
    public function create(CreateTaskRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tasks/{model_id}', name: 'retrieve_task', methods: ['GET'])]
    public function retrieve(RetrieveTaskRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tasks/{model_id}', name: 'edit_task', methods: ['PATCH'])]
    public function edit(EditTaskRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/tasks/{model_id}', name: 'delete_task', methods: ['DELETE'])]
    public function delete(DeleteTaskRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
