<?php

namespace App\EntryPoint\Controller\Api;

use App\AccountsReceivable\Api\ListContactRolesRoute;
use App\AccountsReceivable\Api\CreateContactRoleRoute;
use App\AccountsReceivable\Api\DeleteContactRoleRoute;
use App\AccountsReceivable\Api\EditContactRoleRoute;
use App\AccountsReceivable\Api\RetrieveContactRoleRoute;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(host: '%app.api_domain%', name: 'api_')]
class ContactRoleApiController extends AbstractApiController
{
    #[Route(path: '/contact_roles', name: 'list_contact_roles', methods: ['GET'])]
    public function listAll(ListContactRolesRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/contact_roles', name: 'create_contact_role', methods: ['POST'])]
    public function create(CreateContactRoleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/contact_roles/{model_id}', name: 'retrieve_contact_role', methods: ['GET'])]
    public function retrieve(RetrieveContactRoleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/contact_roles/{model_id}', name: 'edit_contact_role', methods: ['PATCH'])]
    public function edit(EditContactRoleRoute $route): Response
    {
        return $this->runRoute($route);
    }

    #[Route(path: '/contact_roles/{model_id}', name: 'delete_contact_role', methods: ['DELETE'])]
    public function delete(DeleteContactRoleRoute $route): Response
    {
        return $this->runRoute($route);
    }
}
