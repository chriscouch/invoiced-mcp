<?php

namespace App\EntryPoint\Controller\Api;

use App\Companies\Api\CreateRoleRoute;
use App\Companies\Api\DeleteRoleRoute;
use App\Companies\Api\EditRoleRoute;
use App\Companies\Api\ListRolesRoute;
use App\Companies\Api\RetrieveRoleRoute;
use App\Companies\Models\Role;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class RoleApiController extends AbstractApiController
{
    #[Route(path: '/roles', name: 'list_roles', methods: ['GET'])]
    public function listAll(ListRolesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/roles', name: 'create_role', methods: ['POST'])]
    public function create(CreateRoleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/roles/{model_id}', name: 'retrieve_role', methods: ['GET'])]
    public function retrieve(RetrieveRoleRoute $route, string $model_id): Response
    {
        $this->setModel($route, $model_id);

        return $this->runRoute($route);
    }

    #[Route(path: '/roles/{model_id}', name: 'edit_role', methods: ['PATCH'])]
    public function edit(EditRoleRoute $route, string $model_id): Response
    {
        $this->setModel($route, $model_id);

        return $this->runRoute($route);
    }

    #[Route(path: '/roles/{model_id}', name: 'delete_role', methods: ['DELETE'])]
    public function delete(DeleteRoleRoute $route, string $model_id): Response
    {
        $this->setModel($route, $model_id);

        return $this->runRoute($route);
    }

    private function setModel(AbstractModelApiRoute $route, string $model_id): void
    {
        $model = Role::findById($model_id);
        if ($model) {
            $route->setModel($model);
        }
    }
}
